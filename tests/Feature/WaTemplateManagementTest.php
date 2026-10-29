<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\WaMessageTemplate;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds nine default WhatsApp templates through the migrations', function () {
    expect(WaMessageTemplate::count())->toBe(9);
    foreach (['Reset Passphrase', 'Pembaruan', 'Pendaftaran'] as $service) {
        foreach (['unverified', 'followup', 'completed'] as $event) {
            $this->assertDatabaseHas('wa_message_templates', ['service_code' => $service, 'event_type' => $event]);
        }
    }
});

it('only lets admins view and update message presets', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $template = WaMessageTemplate::firstOrFail();
    $body = 'Yth. {nama_pemohon}, tiket {nomor_tiket} ditangani oleh {nama_verifikator}. Silakan pantau email resmi.';

    $this->get(route('admin.templates.index'))->assertRedirect(route('login'));
    $this->actingAs($verifier)->get(route('admin.templates.index'))->assertForbidden();
    $this->actingAs($verifier)
        ->put(route('admin.templates.update', $template), ['body' => $body])
        ->assertForbidden();
    $this->actingAs($admin)->get(route('admin.templates.index'))->assertOk()->assertSee('Preset Pesan WhatsApp');
    $this->actingAs($admin)
        ->put(route('admin.templates.update', $template), ['body' => $body])
        ->assertRedirect();
    expect($template->fresh()->body)->toBe($body);
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'action' => 'admin_updated_wa_template',
        'target_type' => 'wa_message_template',
        'target_id' => $template->id
    ]);
});

it('rejects unknown or missing required placeholders', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $template = WaMessageTemplate::firstOrFail();
    $this->actingAs($admin)
        ->put(route('admin.templates.update', $template), [
            'body' => 'Nama {nama_pemohon}, nomor {nomor_tiket}. Pesan panjang tanpa nama verifikator.'
        ])
        ->assertSessionHasErrors('body');
    $this->actingAs($admin)
        ->put(route('admin.templates.update', $template), [
            'body' => 'Nama {nama_pemohon}, nomor {nomor_tiket}. Petugas {nama_verifikator}. {password_rahasia}'
        ])
        ->assertSessionHasErrors('body');
});

it('renders the admin preset using ticket and verifier data', function () {
    $verifier = User::factory()->create(['role' => 'verifier', 'name' => 'Petugas Uji', 'initials' => 'PU']);
    $template = WaMessageTemplate::where('service_code', 'Pembaruan')->where('event_type', 'completed')->firstOrFail();
    $template->update([
        'body' =>
            'Halo {nama_pemohon}; tiket {nomor_tiket}; {layanan}; petugas {nama_verifikator} ({inisial_verifikator}); {nama_instansi}.'
    ]);
    $ticket = Ticket::create([
        'nama_user' => 'Pemohon Demo',
        'no_wa' => '6281212345678',
        'layanan' => 'Pembaruan',
        'status' => 'Done',
        'source' => 'demo',
        'wa_verification_status' => 'verified',
        'claimed_by' => $verifier->id,
        'ticket_number' => 'TTE-TEST-001'
    ]);
    $message = app(WhatsAppMessage::class)->forCompletedTicket($ticket);
    expect($message)->toContain('Pemohon Demo', 'TTE-TEST-001', 'Petugas Uji (PU)', 'Pembaruan Sertifikat Elektronik');
    $this->actingAs($verifier)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Salin Pesan')
        ->assertSee('completed-wa-message');
});

it('shows copy action but blocks opening WhatsApp for simulated tickets', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = Ticket::create([
        'external_submission_id' => 'TTE-SIMULASI-TEST',
        'nama_user' => 'Pemohon Simulasi',
        'no_wa' => '6280000010001',
        'layanan' => 'Reset Passphrase',
        'source' => 'simulated',
        'status' => 'Done',
        'wa_verification_status' => 'verified',
        'claimed_by' => $verifier->id,
        'ticket_number' => 'TTE-SIM-TEST'
    ]);
    $this->actingAs($verifier)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('DATA SIMULASI')
        ->assertSee('Salin Pesan')
        ->assertDontSee('Buka WhatsApp Web');
    $this->actingAs($verifier)->post(route('tickets.whatsapp', $ticket))->assertForbidden();
});
