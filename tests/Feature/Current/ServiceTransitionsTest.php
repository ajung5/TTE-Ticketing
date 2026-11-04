<?php

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rbTransitionTicket(User $owner, string $service = 'Reset Passphrase'): Ticket
{
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

function rbAmsInput(string $validity, string $wa = 'verified'): array
{
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
