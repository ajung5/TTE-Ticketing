<?php

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Models\WaMessageTemplate;
use App\Models\WaNotification;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

function amsV16Ticket(User $verifier, string $service = 'Reset Passphrase'): Ticket
{
    return Ticket::create([
        'nama_user' => 'Pemohon Uji',
        'no_wa' => '6285812341234',
        'email' => 'pemohon@subang.go.id',
        'layanan' => $service,
        'status' => 'In Progress',
        'source' => 'direct',
        'claimed_by' => $verifier->id,
    ]);
}

it('keeps the verification UI dropdown-only and registers exactly eight templates', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV16Ticket($verifier);
    expect(WaMessageTemplate::EVENTS)->toHaveCount(8);
    $this->actingAs($verifier)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('name="wa_verification_status"', false)
        ->assertSee('name="activation_status"', false)
        ->assertSee('name="certificate_issuance_status"', false)
        ->assertSee('name="recommended_service"', false)
        ->assertSee('ams-workflow.js');
    $this->assertDatabaseHas('wa_message_templates', [
        'event_type' => 'certificate_not_issued',
        'service_code' => '*',
    ]);
    $this->assertDatabaseHas('wa_message_templates', [
        'event_type' => 'service_mismatch',
        'service_code' => '*',
    ]);
});

it('skips activation and issuance when WA is verified and selects reset notification', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV16Ticket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'wa_verification_status' => 'verified',
            'activation_status' => 'not_activated', // Should be ignored because the step is skipped.
            'certificate_issuance_status' => 'not_issued',
        ])
        ->assertRedirect();
    expect($ticket->fresh()->activation_status)->toBeNull();
    expect($ticket->fresh()->certificate_issuance_status)->toBeNull();
    $this->actingAs($verifier)->get(route('tickets.show', $ticket))->assertSee('Reset Passphrase');
    $this->actingAs($verifier)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', [
        'ticket_id' => $ticket->id,
        'event_type' => 'reset',
    ]);
});

it('refuses an incomplete unverified WA inspection', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV16Ticket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'wa_verification_status' => 'unverified',
            'activation_status' => 'activated',
        ])
        ->assertSessionHasErrors('certificate_issuance_status');
    expect($ticket->fresh()->wa_checked_at)->toBeNull();
});

it('notifies when SE is not yet issued and blocks completion until rechecked', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV16Ticket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'wa_verification_status' => 'unverified',
            'activation_status' => 'activated',
            'certificate_issuance_status' => 'not_issued',
        ])
        ->assertRedirect();
    $this->actingAs($verifier)->get(route('tickets.show', $ticket))->assertSee('SE Belum Terbit');
    $this->actingAs($verifier)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($verifier)
        ->post(route('tickets.whatsapp.service.confirm', $ticket))
        ->assertRedirect();
    $this->actingAs($verifier)->post(route('tickets.complete', $ticket))->assertStatus(409);
    expect($ticket->fresh()->status)->toBe('In Progress');
});

it('returns a mismatched reset request administratively and never marks it Done', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV16Ticket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'wa_verification_status' => 'unverified',
            'activation_status' => 'not_activated',
        ])
        ->assertRedirect();
    expect($ticket->fresh()->recommended_service)->toBe('Pendaftaran');
    $this->actingAs($verifier)
        ->get(route('tickets.show', $ticket))
        ->assertSee('Perbaikan Jenis Permohonan');
    $message = app(WhatsAppMessage::class)->forServiceTicket($ticket->fresh(), 'service_mismatch');
    expect($message)->toContain(route('public.form'));
    $this->actingAs($verifier)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($verifier)
        ->post(route('tickets.whatsapp.service.confirm', $ticket))
        ->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Returned');
    expect($ticket->fresh()->completed_at)->toBeNull();
    $this->assertDatabaseHas('ticket_activities', [
        'ticket_id' => $ticket->id,
        'action' => 'returned_for_correction',
    ]);
    $this->actingAs($verifier)->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->actingAs($verifier)
        ->get(route('tickets.show', $ticket))
        ->assertDontSee('completed-wa-message');
});

it(
    'requires an explicit service recommendation when issuance was requested but SE exists',
    function () {
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = amsV16Ticket($verifier, 'Pendaftaran');
        $this->actingAs($verifier)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'wa_verification_status' => 'verified',
            ])
            ->assertSessionHasErrors('recommended_service');
        $this->actingAs($verifier)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'wa_verification_status' => 'verified',
                'recommended_service' => 'Pembaruan',
            ])
            ->assertRedirect();
        $this->actingAs($verifier)
            ->post(route('tickets.whatsapp.service', $ticket))
            ->assertRedirect();
        $this->assertDatabaseHas('wa_notifications', [
            'ticket_id' => $ticket->id,
            'event_type' => 'service_mismatch',
        ]);
        expect($ticket->fresh()->recommended_service)->toBe('Pembaruan');
    },
);

it('preserves legitimate issuance flow after the user was previously unregistered', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV16Ticket($verifier, 'Pendaftaran');
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'wa_verification_status' => 'unverified',
            'activation_status' => 'not_activated',
        ])
        ->assertRedirect();
    $this->actingAs($verifier)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($verifier)
        ->post(route('tickets.whatsapp.service.confirm', $ticket))
        ->assertRedirect();
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'wa_verification_status' => 'verified',
        ])
        ->assertRedirect();
    $this->actingAs($verifier)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', [
        'ticket_id' => $ticket->id,
        'event_type' => 'registration',
    ]);
});
