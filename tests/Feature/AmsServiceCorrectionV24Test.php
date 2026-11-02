<?php

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Models\WaMessageTemplate;
use App\Models\WaNotification;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function amsV24Ticket(User $owner, string $service): Ticket
{
    return Ticket::create([
        'nama_user' => 'Pemohon Uji AMS V24',
        'no_wa' => '6281212345678',
        'email' => 'pemohon@example.test',
        'uptd' => '',
        'layanan' => $service,
        'status' => 'In Progress',
        'source' => 'direct',
        'claimed_by' => $owner->id,
    ]);
}

it('returns reset or renewal without any issued certificate only after the new notice is confirmed', function (string $service, string $wa) {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV24Ticket($owner, $service);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => $wa,
        'activation_status' => 'activated',
        'certificate_issuance_status' => 'not_issued',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->recommended_service)->toBe('Pendaftaran');
    $this->get(route('tickets.show', $ticket))->assertOk()
        ->assertSee('Penyesuaian ke Penerbitan SE (Belum Pernah Terbit)')
        ->assertSee('Kembalikan Permohonan')->assertDontSee('Tandai Selesai');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertStatus(409);
    $this->postJson(route('tickets.whatsapp.service', $ticket))->assertOk()->assertJsonStructure(['url']);
    $this->assertDatabaseHas('wa_notifications', ['ticket_id' => $ticket->id, 'event_type' => 'issuance_required']);
    $this->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('In Progress');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->post(route('tickets.return-for-correction', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Returned');
    expect($ticket->fresh()->layanan)->toBe($service);
    expect($ticket->fresh()->completed_at)->toBeNull();
    $this->get(route('tickets.show', $ticket))->assertOk()->assertDontSee('completed-wa-message');
})->with([
    ['Reset Passphrase', 'verified'], ['Reset Passphrase', 'unverified'],
    ['Pembaruan', 'verified'], ['Pembaruan', 'unverified'],
]);

it('requires a fresh check for legacy not-issued reset tickets and rejects the old confirmation', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV24Ticket($owner, 'Reset Passphrase');
    $ticket->forceFill([
        'wa_verification_status' => 'verified', 'certificate_issuance_status' => 'not_issued',
        'wa_checked_at' => now(), 'recommended_service' => null,
    ])->save();
    WaNotification::create([
        'ticket_id' => $ticket->id, 'verifier_id' => $owner->id, 'event_type' => 'certificate_not_issued',
        'message' => 'Pesan lama', 'opened_at' => now(), 'confirmed_at' => now(),
    ]);
    $this->actingAs($owner)->get(route('tickets.show', $ticket))->assertOk()
        ->assertSee('Periksa ulang riwayat penerbitan SE.')->assertDontSee('id="service-wa-message"', false);
    $this->post(route('tickets.whatsapp.service', $ticket))->assertStatus(409);
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertStatus(409);
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified', 'certificate_issuance_status' => 'not_issued',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('wa_notifications', [
        'ticket_id' => $ticket->id, 'event_type' => 'certificate_not_issued', 'confirmed_at' => null,
    ]);
    $this->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
});

it('keeps previously issued certificates on their chosen service even with an old pending-issuance history', function (string $service, string $event) {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV24Ticket($owner, $service);
    TicketActivity::create([
        'ticket_id' => $ticket->id, 'actor_id' => $owner->id, 'action' => 'wa_service_confirmed',
        'note' => WaMessageTemplate::EVENTS['certificate_not_issued'],
    ]);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified', 'certificate_issuance_status' => 'issued',
    ])->assertSessionHasNoErrors();
    expect($ticket->fresh()->recommended_service)->toBeNull();
    $this->get(route('tickets.show', $ticket))->assertOk()
        ->assertDontSee('SE Sudah Terbit — Siap Ditandai Selesai');
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', ['ticket_id' => $ticket->id, 'event_type' => $event]);
    $this->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
})->with([['Reset Passphrase', 'reset'], ['Pembaruan', 'renewal']]);

it('allows an explicit reset-renewal correction after clarification and invalidates it when withdrawn', function (string $service, string $recommended, string $wa) {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV24Ticket($owner, $service);
    $inspection = ['wa_verification_status' => $wa, 'activation_status' => 'activated', 'certificate_issuance_status' => 'issued'];
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), $inspection + [
        'recommended_service' => $recommended,
    ])->assertSessionHasNoErrors();
    expect($ticket->fresh()->recommended_service)->toBe($recommended);
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', ['ticket_id' => $ticket->id, 'event_type' => 'service_mismatch']);
    $this->post(route('tickets.verify-whatsapp', $ticket), $inspection)->assertSessionHasNoErrors();
    expect($ticket->fresh()->recommended_service)->toBeNull();
    $this->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    $this->assertDatabaseHas('wa_notifications', ['ticket_id' => $ticket->id, 'event_type' => 'service_mismatch', 'confirmed_at' => null]);
})->with([
    ['Reset Passphrase', 'Pembaruan', 'verified'], ['Pembaruan', 'Reset Passphrase', 'verified'],
    ['Reset Passphrase', 'Pembaruan', 'unverified'], ['Pembaruan', 'Reset Passphrase', 'unverified'],
]);

it('rejects a recommendation equal to the selected service or a fabricated issuance recommendation', function (string $recommended) {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV24Ticket($owner, 'Reset Passphrase');
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified', 'certificate_issuance_status' => 'issued',
        'recommended_service' => $recommended,
    ])->assertSessionHasErrors('recommended_service');
    expect($ticket->fresh()->wa_checked_at)->toBeNull();
})->with(['Reset Passphrase', 'Pendaftaran']);

it('preserves every existing preset and the customized new preset on repeated migration and rollback', function () {
    $event = 'issuance_required';
    DB::table('wa_message_templates')->where('event_type', '!=', $event)->update([
        'body' => 'Kustom lama {nama_pemohon} {nomor_tiket} {nama_verifikator}',
    ]);
    $before = DB::table('wa_message_templates')->where('event_type', '!=', $event)->orderBy('id')->get()->toJson();
    DB::table('wa_message_templates')->where('event_type', $event)->delete();
    $migration = require database_path('migrations/2026_11_02_220000_add_no_certificate_service_correction_preset.php');
    $migration->up();
    $this->assertDatabaseHas('wa_message_templates', ['service_code' => '*', 'event_type' => $event]);
    expect(DB::table('wa_message_templates')->where('event_type', '!=', $event)->orderBy('id')->get()->toJson())->toBe($before);
    $custom = 'Kustom baru {nama_pemohon} {nomor_tiket} {nama_verifikator}';
    DB::table('wa_message_templates')->where('event_type', $event)->update(['body' => $custom]);
    $migration->up();
    $migration->down();
    expect(DB::table('wa_message_templates')->where('event_type', $event)->count())->toBe(1);
    expect(DB::table('wa_message_templates')->where('event_type', $event)->value('body'))->toBe($custom);
    expect(DB::table('wa_message_templates')->where('event_type', '!=', $event)->orderBy('id')->get()->toJson())->toBe($before);
});

it('lets only admins edit the added preset and renders that customization', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create(['role' => 'verifier']);
    $template = WaMessageTemplate::where('service_code', '*')->where('event_type', 'issuance_required')->firstOrFail();
    $body = 'Kustom {nama_pemohon}, tiket {nomor_tiket}. Petugas {nama_verifikator}. Ajukan {layanan_disarankan} melalui {form_url}.';
    $this->actingAs($owner)->put(route('admin.templates.update', $template), ['body' => $body])->assertForbidden();
    $this->actingAs($admin)->get(route('admin.templates.index'))->assertOk()
        ->assertSee('Penyesuaian ke Penerbitan SE (Belum Pernah Terbit)')->assertSee('9 jenis notifikasi');
    $this->put(route('admin.templates.update', $template), ['body' => $body])->assertRedirect()->assertSessionHasNoErrors();
    $ticket = amsV24Ticket($owner, 'Pembaruan');
    $ticket->forceFill(['recommended_service' => 'Pendaftaran'])->save();
    $message = app(WhatsAppMessage::class)->forServiceTicket($ticket, 'issuance_required');
    expect($message)->toContain('Kustom', 'Penerbitan Sertifikat Elektronik', route('public.form'));
});

it('blocks administrators and other verifiers from operating the new correction flow', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV24Ticket($owner, 'Pembaruan');
    foreach (['admin', 'verifier'] as $role) {
        $other = User::factory()->create(['role' => $role]);
        $this->actingAs($other)->post(route('tickets.verify-whatsapp', $ticket), [
            'wa_verification_status' => 'verified', 'certificate_issuance_status' => 'not_issued',
        ])->assertForbidden();
        foreach (['tickets.whatsapp.service', 'tickets.whatsapp.service.confirm', 'tickets.return-for-correction'] as $route) {
            $this->post(route($route, $ticket))->assertForbidden();
        }
    }
});
