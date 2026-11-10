<?php

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Models\WaMessageTemplate;
use App\Models\WaNotification;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Source: Current/AmsBusinessRulesTest.php
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

// Source: AmsActualProgressV35Test.php
function amsV35Ticket(User $owner, string $service = 'Reset Passphrase'): Ticket {
    return Ticket::create([
        'nama_user' => 'Pemohon AMS Aktual',
        'no_wa' => '6281212345678',
        'email' => 'pengujian@example.test',
        'layanan' => $service,
        'source' => 'direct',
        'status' => 'In Progress',
        'claimed_by' => $owner->id,
    ]);
}

it('accepts revoke as explicit certificate status and blocks completion', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV35Ticket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'revoked',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    expect($ticket->fresh()->certificate_validity_status)->toBe('revoked');
    expect($ticket->fresh()->actual_process)->toBe('certificate_reissuance');
    expect($ticket->fresh()->recommended_service)->toBe('Penerbitan Ulang');
    $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('SE Revoke');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    expect($ticket->fresh()->status)->toBe('In Progress');
});

it('uses one ticket from revoked to reissued and sends the actual outcome', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV35Ticket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'revoked',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated',
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued',
        'certificate_validity_status' => 'active',
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertSee('Siap Ditandai Selesai');
    $this->post(route('tickets.complete', $ticket))->assertRedirect();
    $record = $ticket->fresh();
    expect($record->status)->toBe('Done');
    expect($record->service_outcome)->toBe('certificate_reissued');
    expect($record->actual_process)->toBe('certificate_reissuance');
    expect(app(WhatsAppMessage::class)->forCompletedTicket($record))
        ->toContain('berhasil diterbitkan ulang')
        ->not->toContain('Reset Passphrase TTE');
    expect(
        TicketActivity::where('ticket_id', $ticket->id)->where('action', 'ams_checked')->count(),
    )->toBe(2);
});

it('records actual issuance rather than original reset for a not-issued certificate', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV35Ticket($verifier);
    $originalNumber = $ticket->ticket_number;
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'unverified',
            'certificate_issuance_status' => 'not_issued',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    expect($ticket->fresh()->actual_process)->toBe('set_passphrase');
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated',
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued',
        'certificate_validity_status' => 'active',
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $this->post(route('tickets.complete', $ticket))->assertRedirect();
    expect($ticket->fresh()->ticket_number)->toBe($originalNumber);
    expect($ticket->fresh()->layanan)->toBe('Reset Passphrase');
    expect($ticket->fresh()->service_outcome)->toBe('certificate_issued');
    expect(app(WhatsAppMessage::class)->forCompletedTicket($ticket->fresh()))
        ->toContain('berhasil diterbitkan')
        ->not->toContain('Reset Passphrase TTE');
});

it('rejects issued without explicit validity and invalid revoked for unissued', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV35Ticket($verifier);
    $this->actingAs($verifier)
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
        'certificate_validity_status' => 'revoked',
    ])->assertSessionHasErrors('certificate_validity_status');
    expect($ticket->fresh()->wa_checked_at)->toBeNull();
});

it('shows revoke monitoring only to admin and maintains manual AMS verification', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = amsV35Ticket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'unverified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'revoked',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('SE Revoke');
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated',
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued',
        'certificate_validity_status' => 'active',
    ])->assertForbidden();
});

it('maintains a preset for every active notification including revoked', function () {
    expect(WaMessageTemplate::EVENTS)->toHaveKey('certificate_revoked');
    foreach (WaMessageTemplate::TEMPLATE_SERVICE_CODES as $event => $serviceCode) {
        $this->assertDatabaseHas('wa_message_templates', [
            'service_code' => $serviceCode,
            'event_type' => $event,
        ]);
    }
});

// Source: Current/ServiceTransitionsTest.php
function rbTransitionTicket(User $owner, string $service = 'Reset Passphrase'): Ticket {
    return Ticket::create([
        'nama_user' => 'Pemohon Transisi',
        'no_wa' => '6281212345678',
        'email' => 'transisi@example.test',
        'layanan' => $service,
        'ticket_number' => 'RB-TICKET-001',
        'source' => 'direct',
        'status' => 'In Progress',
        'claimed_by' => $owner->id,
    ]);
}

function rbAmsInput(string $validity, string $wa = 'verified'): array {
    return [
        'activation_status' => 'activated',
        'wa_verification_status' => $wa,
        'certificate_issuance_status' => $validity === 'not_issued' ? 'not_issued' : 'issued',
    ] + ($validity === 'not_issued' ? [] : ['certificate_validity_status' => $validity]);
}

it('retains the original ticket and identifies actual issuance after SE becomes active', function (
    string $service,
) {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbTransitionTicket($owner, $service);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), rbAmsInput('not_issued', 'unverified'))
        ->assertSessionHasNoErrors();
    $this->post(
        route('tickets.verify-whatsapp', $ticket),
        rbAmsInput('active'),
    )->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertSee('Siap Ditandai Selesai');
    $this->post(route('tickets.complete', $ticket))->assertRedirect();
    $record = $ticket->fresh();
    expect($record->ticket_number)
        ->toBe('RB-TICKET-001')
        ->and($record->layanan)
        ->toBe($service)
        ->and($record->status)
        ->toBe('Done')
        ->and($record->actual_process)
        ->toBe('certificate_issuance')
        ->and($record->service_outcome)
        ->toBe('certificate_issued');
    expect(
        TicketActivity::where('ticket_id', $ticket->id)->where('action', 'ams_checked')->count(),
    )->toBe(2);
})->with(['Reset Passphrase', 'Pembaruan', 'Pendaftaran']);

it('preserves revoked-to-active reissuance as actual outcome on the same ticket', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbTransitionTicket($owner, 'Reset Passphrase');
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), rbAmsInput('revoked'))
        ->assertSessionHasNoErrors();
    $this->post(
        route('tickets.verify-whatsapp', $ticket),
        rbAmsInput('active'),
    )->assertSessionHasNoErrors();
    $this->post(route('tickets.complete', $ticket))->assertRedirect();
    $record = $ticket->fresh();
    expect($record->ticket_number)
        ->toBe('RB-TICKET-001')
        ->and($record->status)
        ->toBe('Done')
        ->and($record->actual_process)
        ->toBe('certificate_reissuance')
        ->and($record->service_outcome)
        ->toBe('certificate_reissued');
});

it('preserves expired-to-active renewal outcome on the same ticket', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbTransitionTicket($owner, 'Pembaruan');
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), rbAmsInput('expired'))
        ->assertSessionHasNoErrors();
    $this->post(
        route('tickets.verify-whatsapp', $ticket),
        rbAmsInput('active'),
    )->assertSessionHasNoErrors();
    $this->post(route('tickets.complete', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)
        ->toBe('Done')
        ->and($ticket->fresh()->service_outcome)
        ->toBe('certificate_renewed')
        ->and($ticket->fresh()->actual_process)
        ->toBe('certificate_renewal');
});

it('records reset only when the ticket is handled as an active-certificate reset', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbTransitionTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), rbAmsInput('active'))
        ->assertSessionHasNoErrors();
    $this->post(route('tickets.complete', $ticket))->assertRedirect();
    expect($ticket->fresh()->service_outcome)
        ->toBe('passphrase_reset')
        ->and($ticket->fresh()->actual_process)
        ->toBe('reset_passphrase');
});

it(
    'requires correction notification confirmation before returning a wrong-service ticket',
    function () {
        $owner = User::factory()->create(['role' => 'verifier']);
        $ticket = rbTransitionTicket($owner);
        $this->actingAs($owner)
            ->post(route('tickets.verify-whatsapp', $ticket), rbAmsInput('expired'))
            ->assertSessionHasNoErrors();
        $this->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
        $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
        $this->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
        $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
        $this->post(route('tickets.return-for-correction', $ticket))->assertRedirect();
        $record = $ticket->fresh();
        expect($record->status)
            ->toBe('Returned')
            ->and($record->completed_at)
            ->toBeNull()
            ->and($record->service_outcome)
            ->toBeNull()
            ->and($record->recommended_service)
            ->toBe('Pembaruan');
    },
);

it('does not close a returned ticket as Done or create a false service outcome', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbTransitionTicket($owner);
    $ticket->update(['status' => 'Returned']);
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
    expect($ticket->fresh()->status)
        ->toBe('Returned')
        ->and($ticket->fresh()->service_outcome)
        ->toBeNull();
});

it('does not permit completing the same ticket twice', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbTransitionTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), rbAmsInput('active'))
        ->assertSessionHasNoErrors();
    $this->post(route('tickets.complete', $ticket))->assertRedirect();
    $completedAt = $ticket->fresh()->completed_at;
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    expect($ticket->fresh()->status)
        ->toBe('Done')
        ->and($ticket->fresh()->completed_at->equalTo($completedAt))
        ->toBeTrue();
});

it('forbids service completion by a different verifier or administrator', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = rbTransitionTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), rbAmsInput('active'))
        ->assertSessionHasNoErrors();
    $this->actingAs($other)->post(route('tickets.complete', $ticket))->assertForbidden();
    $this->actingAs($admin)->post(route('tickets.complete', $ticket))->assertForbidden();
    expect($ticket->fresh()->status)->toBe('In Progress');
});

it('invalidates a previous correction when a new AMS inspection changes the decision', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbTransitionTicket($owner, 'Reset Passphrase');
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), rbAmsInput('expired'))
        ->assertSessionHasNoErrors();
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->post(
        route('tickets.verify-whatsapp', $ticket),
        rbAmsInput('active'),
    )->assertSessionHasNoErrors();
    $this->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    expect($ticket->fresh()->status)->toBe('In Progress');
});

// Source: TteActivationFooterV60Test.php
function v60Ticket(User $verifier, string $service): Ticket {
    return Ticket::create([
        'nama_user' => 'Pemohon Pengujian V60',
        'no_wa' => '6281212345678',
        'email' => 'contoh@example.test',
        'layanan' => $service,
        'source' => 'direct',
        'status' => 'In Progress',
        'claimed_by' => $verifier->id,
        'ticket_number' => 'TTE-V60-' . uniqid(),
    ]);
}

it(
    'finishes reset and renewal after confirmed activation notice and AMS recheck without an intermediate service notice',
    function (string $service) {
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = v60Ticket($verifier, $service);
        $originalNumber = $ticket->ticket_number;
        $this->actingAs($verifier)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'activation_status' => 'not_activated',
            ])
            ->assertSessionHasNoErrors();
        $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
        $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
        $this->assertDatabaseHas('wa_notifications', [
            'ticket_id' => $ticket->id,
            'event_type' => 'activation_required',
        ]);
        $this->post(route('tickets.complete', $ticket))->assertStatus(409);

        $this->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Siap Ditandai Selesai')
            ->assertSee('Tandai')
            ->assertDontSee('id="tte-service-notification"', false);
        expect($ticket->fresh()->actual_process)->toBe('certificate_issuance');
        $this->post(route('tickets.complete', $ticket))->assertRedirect();
        $done = $ticket->fresh();
        expect($done->status)
            ->toBe('Done')
            ->and($done->ticket_number)
            ->toBe($originalNumber)
            ->and($done->layanan)
            ->toBe($service)
            ->and($done->service_outcome)
            ->toBe('certificate_issued');
        expect(app(WhatsAppMessage::class)->forCompletedTicket($done))
            ->toContain('berhasil diterbitkan')
            ->toContain('survei');

        // Pesan akhir tetap perlu benar-benar dikirim dan dikonfirmasi manual.
        $this->post(route('tickets.whatsapp', $ticket))->assertRedirect();
        $notice = WaNotification::where('ticket_id', $ticket->id)
            ->where('event_type', 'completed')
            ->firstOrFail();
        expect($notice->opened_at)->not->toBeNull()->and($notice->confirmed_at)->toBeNull();
        $this->post(route('tickets.whatsapp.confirm', $ticket))->assertRedirect();
        expect($notice->fresh()->confirmed_at)->not->toBeNull();
    },
)->with(['Reset Passphrase', 'Pembaruan']);

it(
    'does not permit completion after activation without verified WA or active issued certificate',
    function () {
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = v60Ticket($verifier, 'Reset Passphrase');
        $this->actingAs($verifier)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'activation_status' => 'not_activated',
            ])
            ->assertSessionHasNoErrors();

        $this->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'unverified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
        ])->assertSessionHasNoErrors();
        $this->post(route('tickets.complete', $ticket))->assertStatus(409);

        $this->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'expired',
        ])->assertSessionHasNoErrors();
        $this->post(route('tickets.complete', $ticket))->assertStatus(409);
        expect($ticket->fresh()->status)->toBe('In Progress');
    },
);

it(
    'does not bypass the normal reset or renewal notification when no activation history exists',
    function (string $service) {
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = v60Ticket($verifier, $service);
        $this->actingAs($verifier)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'activation_status' => 'activated',
                'wa_verification_status' => 'verified',
                'certificate_issuance_status' => 'issued',
                'certificate_validity_status' => 'active',
            ])
            ->assertSessionHasNoErrors();
        $this->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('id="tte-service-notification"', false);
        expect(
            TicketActivity::where('ticket_id', $ticket->id)
                ->where('action', 'wa_service_confirmed')
                ->exists(),
        )->toBeFalse();
    },
)->with(['Reset Passphrase', 'Pembaruan']);

it(
    'renders copyright on the left and app version on the right for public, admin and verifier',
    function () {
        config()->set('tte.version', '3.2.1');
        $copyright = 'Copyright@DiskominfoSubang 2026-' . now('Asia/Jakarta')->year;
        $check = function ($response) use ($copyright) {
            $response
                ->assertOk()
                ->assertSee($copyright)
                ->assertSee('class="tte-site-footer"', false)
                ->assertSee('class="tte-site-footer-copy"', false)
                ->assertSee('class="tte-site-footer-version"', false)
                ->assertSee('v3.2.1')
                ->assertSee('tte-app.css')
                ->assertSee('tte-app.css?v=' . \App\Support\VersionedAsset::version('css/tte-app.css'));
            $html = $response->getContent();
            expect(strpos($html, $copyright))->toBeLessThan(strpos($html, 'v3.2.1'));
        };
        $check($this->get(route('public.form')));
        $admin = User::factory()->create(['role' => 'admin']);
        $check($this->actingAs($admin)->get(route('admin.dashboard')));
        $verifier = User::factory()->create(['role' => 'verifier']);
        $check($this->actingAs($verifier)->get(route('tickets.index')));
    },
);
