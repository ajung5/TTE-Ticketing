<?php

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Models\WaMessageTemplate;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function amsV35Ticket(User $owner, string $service = 'Reset Passphrase'): Ticket
{
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
