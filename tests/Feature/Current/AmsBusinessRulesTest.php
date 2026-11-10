<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rbAmsTicket(User $owner, string $service = 'Reset Passphrase'): Ticket {
    return Ticket::create([
        'nama_user' => 'Pemohon Rebaseline AMS',
        'no_wa' => '6281212345678',
        'email' => 'ams@example.test',
        'layanan' => $service,
        'status' => 'In Progress',
        'source' => 'direct',
        'claimed_by' => $owner->id,
    ]);
}

it('requires explicit account activation before other AMS checks', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
        ])
        ->assertSessionHasErrors('activation_status');
    expect($ticket->fresh()->wa_checked_at)->toBeNull();
});

it('leaves WA and certificate unchecked when account is not activated', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'not_activated',
        ])
        ->assertSessionHasNoErrors();
    $record = $ticket->fresh();
    expect($record->wa_verification_status)
        ->toBe('unchecked')
        ->and($record->certificate_issuance_status)
        ->toBeNull()
        ->and($record->certificate_validity_status)
        ->toBeNull()
        ->and($record->actual_process)
        ->toBe('account_activation');
    $this->get(route('tickets.show', $ticket))->assertSee('Aktivasi Akun Diperlukan');
});

it('keeps registration in progress until Notifikasi 4 is confirmed', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner, 'Pendaftaran');
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'not_activated',
        ])
        ->assertSessionHasNoErrors();
    expect($ticket->fresh()->actual_process)
        ->toBe('account_activation')
        ->and($ticket->fresh()->status)
        ->toBe('In Progress')
        ->and($ticket->fresh()->wa_verification_status)
        ->toBe('unchecked');
    $this->get(route('tickets.show', $ticket))
        ->assertSee('Pendaftaran Diproses')
        ->assertDontSee('Perbaikan Jenis Permohonan');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertStatus(409);
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('In Progress');
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)
        ->toBe('Done')
        ->and($ticket->fresh()->service_outcome)
        ->toBe('registration_processed');
});

it('rejects a fabricated certificate decision for an unactivated account', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'not_activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
        ])
        ->assertSessionHasErrors('activation_status');
    expect($ticket->fresh()->wa_checked_at)->toBeNull();
});

it('requires explicit WA verification for an activated account', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'certificate_issuance_status' => 'not_issued',
        ])
        ->assertSessionHasErrors('wa_verification_status');
});

it('requires issuance status after activation and WA assessment', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
        ])
        ->assertSessionHasErrors('certificate_issuance_status');
});

it('requires certificate validity only when issued', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
        ])
        ->assertSessionHasErrors('certificate_validity_status');
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated',
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'not_issued',
        'certificate_validity_status' => 'active',
    ])->assertSessionHasErrors('certificate_validity_status');
});

it('rejects unknown certificate validity values', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'suspended',
        ])
        ->assertSessionHasErrors('certificate_validity_status');
});

it(
    'keeps the same ticket for pending issuance even if the original service was different',
    function (string $service) {
        $owner = User::factory()->create(['role' => 'verifier']);
        $ticket = rbAmsTicket($owner, $service);
        $this->actingAs($owner)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'activation_status' => 'activated',
                'wa_verification_status' => 'unverified',
                'certificate_issuance_status' => 'not_issued',
            ])
            ->assertSessionHasNoErrors();
        expect($ticket->fresh()->status)
            ->toBe('In Progress')
            ->and($ticket->fresh()->recommended_service)
            ->toBeNull()
            ->and($ticket->fresh()->actual_process)
            ->toBe('set_passphrase');
        $this->get(route('tickets.show', $ticket))->assertSee('SE Belum Terbit');
        $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    },
)->with(['Reset Passphrase', 'Pembaruan', 'Pendaftaran']);

it('prioritizes WA notice over regular service when certificate is active', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'unverified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
        ])
        ->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertSee('Nomor WA Belum Terverifikasi');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
});

it('prioritizes revoke notification over unverified WA and blocks completion', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'unverified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'revoked',
        ])
        ->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertSee('SE Revoke');
    expect($ticket->fresh()->recommended_service)->toBe('Penerbitan Ulang');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
});

it('routes expired reset requests to renewal instead of allowing completion', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'expired',
        ])
        ->assertSessionHasNoErrors();
    expect($ticket->fresh()->recommended_service)
        ->toBe('Pembaruan')
        ->and($ticket->fresh()->service_alignment_status)
        ->toBe('unsuitable');
    $this->get(route('tickets.show', $ticket))->assertSee('Perbaikan Jenis Permohonan');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
});

it('keeps expired renewal in progress until renewed status is verified', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner, 'Pembaruan');
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'expired',
        ])
        ->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertSee('Pembaruan Sertifikat');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
});

it('requires clarification when a registration applicant already has active SE', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner, 'Pendaftaran');
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
        ])
        ->assertSessionHasErrors('recommended_service');
});

it('does not accept a recommendation equal to the requested service', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
            'recommended_service' => 'Reset Passphrase',
        ])
        ->assertSessionHasErrors('recommended_service');
});

it('does not accept an invented recommendation outside supported services', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
            'recommended_service' => 'Pendaftaran',
        ])
        ->assertSessionHasErrors('recommended_service');
});

it('records an explicit wrong-service decision with a supported alternative', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner, 'Reset Passphrase');
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
            'service_alignment_status' => 'unsuitable',
            'recommended_service' => 'Pembaruan',
        ])
        ->assertSessionHasNoErrors();
    expect($ticket->fresh()->service_alignment_status)
        ->toBe('unsuitable')
        ->and($ticket->fresh()->recommended_service)
        ->toBe('Pembaruan');
    $this->get(route('tickets.show', $ticket))->assertSee('Perbaikan Jenis Permohonan');
});

it('does not let a wrong-service status stand without a required recommendation', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbAmsTicket($owner, 'Reset Passphrase');
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
            'service_alignment_status' => 'unsuitable',
        ])
        ->assertSessionHasErrors('recommended_service');
});
