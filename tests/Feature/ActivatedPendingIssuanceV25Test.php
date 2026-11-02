<?php

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Models\WaMessageTemplate;
use App\Models\WaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finishes an activated pending issuance on the same ticket only after SE issuance and WA verification', function (string $service) {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = Ticket::create([
        'nama_user' => 'Pemohon V25', 'no_wa' => '6281212345678', 'email' => 'pemohon@example.test',
        'uptd' => '', 'layanan' => $service, 'status' => 'In Progress', 'source' => 'direct',
        'claimed_by' => $owner->id, 'ticket_number' => 'TTE-V25-001',
    ]);
    $count = Ticket::count();
    $preset = WaMessageTemplate::where('service_code', '*')->where('event_type', 'certificate_not_issued')->firstOrFail();
    $preset->update(['body' => 'Kustom lanjut set passphrase {nama_pemohon}, tiket {nomor_tiket}, petugas {nama_verifikator}.']);
    $presetsBefore = WaMessageTemplate::orderBy('id')->get()->toJson();
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'unverified', 'activation_status' => 'activated',
        'certificate_issuance_status' => 'not_issued',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->recommended_service)->toBeNull();
    $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('SE Belum Terbit')
        ->assertSee('Kustom lanjut set passphrase')->assertSee('tidak perlu mengisi permohonan baru')
        ->assertDontSee('Kembalikan Permohonan');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    $this->postJson(route('tickets.whatsapp.service', $ticket))->assertOk()->assertJsonStructure(['url']);
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('In Progress');
    $this->assertDatabaseHas('wa_notifications', ['ticket_id' => $ticket->id, 'event_type' => 'certificate_not_issued']);
    $this->assertDatabaseMissing('wa_notifications', ['ticket_id' => $ticket->id, 'event_type' => 'issuance_required']);

    // Terbitnya SE tidak otomatis membuat WA terverifikasi.
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'unverified', 'activation_status' => 'activated',
        'certificate_issuance_status' => 'issued',
    ])->assertSessionHasNoErrors();
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('Nomor WA Belum Terverifikasi')
        ->assertDontSee('SE Sudah Terbit — Siap Ditandai Selesai');
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', ['ticket_id' => $ticket->id, 'event_type' => 'unverified']);
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);

    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified', 'certificate_issuance_status' => 'issued',
    ])->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertOk()
        ->assertSee('SE Sudah Terbit — Siap Ditandai Selesai')->assertDontSee('id="service-wa-message"', false);
    $this->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    $this->post(route('tickets.complete', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Done');
    expect($ticket->fresh()->completed_at)->not->toBeNull();
    expect($ticket->fresh()->ticket_number)->toBe('TTE-V25-001');
    expect($ticket->fresh()->layanan)->toBe($service);
    expect(Ticket::count())->toBe($count);
    expect(TicketActivity::where('ticket_id', $ticket->id)->where('action', 'returned_for_correction')->exists())->toBeFalse();
    expect(WaNotification::where('ticket_id', $ticket->id)->whereIn('event_type', ['reset', 'renewal', 'registration'])->exists())->toBeFalse();
    expect(WaMessageTemplate::orderBy('id')->get()->toJson())->toBe($presetsBefore);
    $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('Notifikasi Selesai + Survei');
})->with(['Pendaftaran', 'Reset Passphrase', 'Pembaruan']);

it('keeps completion blocked when WA becomes verified but SE is still pending', function (string $service) {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = Ticket::create([
        'nama_user' => 'Pemohon V25', 'no_wa' => '6281212345678', 'email' => 'pemohon@example.test',
        'uptd' => '', 'layanan' => $service, 'status' => 'In Progress', 'source' => 'direct', 'claimed_by' => $owner->id,
    ]);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'unverified', 'activation_status' => 'activated',
        'certificate_issuance_status' => 'not_issued',
    ])->assertSessionHasNoErrors();
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified', 'certificate_issuance_status' => 'not_issued',
    ])->assertSessionHasNoErrors();
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    expect($ticket->fresh()->status)->toBe('In Progress');
    expect($ticket->fresh()->recommended_service)->toBeNull();
})->with(['Pendaftaran', 'Reset Passphrase', 'Pembaruan']);
