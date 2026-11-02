<?php

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Models\WaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function v21Ticket(User $owner, string $service = 'Reset Passphrase'): Ticket
{
    return Ticket::create([
        'nama_user' => 'Pemohon Pengembalian',
        'no_wa' => '6281212345678',
        'email' => 'pemohon@subang.go.id',
        'layanan' => $service,
        'status' => 'In Progress',
        'source' => 'direct',
        'claimed_by' => $owner->id,
        'ticket_number' => 'TTE-RETURN-001',
    ]);
}

function v21ReturnButtonAttributes(string $html, Ticket $ticket): string
{
    $route = preg_quote(route('tickets.return-for-correction', $ticket), '~');
    preg_match('~<form method="post" action="' . $route . '"[^>]*>.*?<button\b([^>]*)>Kembalikan Permohonan~s', $html, $matches);
    expect($matches)->not->toBeEmpty();
    return $matches[1];
}

it('requires confirmed correction notification before a separate return action', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = v21Ticket($owner);
    $number = $ticket->ticket_number;
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'unverified',
        'activation_status' => 'not_activated',
    ])->assertRedirect();
    $response = $this->actingAs($owner)->get(route('tickets.show', $ticket))->assertOk();
    expect(v21ReturnButtonAttributes($response->getContent(), $ticket))->toContain('disabled');
    $response->assertSee('Kembalikan Permohonan')->assertDontSee('Tandai Selesai');
    $this->actingAs($owner)->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertStatus(409);
    $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('In Progress');
    expect($ticket->fresh()->ticket_number)->toBe($number);
    $html = $this->actingAs($owner)->get(route('tickets.show', $ticket))->assertOk()->getContent();
    expect(v21ReturnButtonAttributes($html, $ticket))->not->toContain('disabled');
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->actingAs($owner)->post(route('tickets.return-for-correction', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Returned');
    expect($ticket->fresh()->ticket_number)->toBe($number);
    expect($ticket->fresh()->completed_at)->toBeNull();
    $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'returned_for_correction']);
    $this->actingAs($owner)->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('Perlu Pengajuan Ulang')->assertDontSee('completed-wa-message');
    $this->actingAs($owner)->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    expect(TicketActivity::where('ticket_id', $ticket->id)
        ->where('action', 'returned_for_correction')->count())->toBe(1);
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
});

it('blocks non-owners and administrators from returning a ticket', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = v21Ticket($owner);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'unverified',
        'activation_status' => 'not_activated',
    ])->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->actingAs($other)->post(route('tickets.return-for-correction', $ticket))->assertForbidden();
    $this->actingAs($admin)->post(route('tickets.return-for-correction', $ticket))->assertForbidden();
    expect($ticket->fresh()->status)->toBe('In Progress');
});

it('prevents returning when AMS outcome changes after a previously confirmed correction notice', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = v21Ticket($owner);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'unverified',
        'activation_status' => 'not_activated',
    ])->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'not_issued',
    ])->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    expect($ticket->fresh()->status)->toBe('In Progress');
    $this->assertDatabaseHas('wa_notifications', [
        'ticket_id' => $ticket->id,
        'event_type' => 'service_mismatch',
        'confirmed_at' => null,
    ]);
});

it('returns an incorrect Pendaftaran ticket only after choosing a corrected service', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = v21Ticket($owner, 'Pendaftaran');
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued',
        'recommended_service' => 'Pembaruan',
    ])->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('In Progress');
    $this->actingAs($owner)->post(route('tickets.return-for-correction', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Returned');
    expect($ticket->fresh()->recommended_service)->toBe('Pembaruan');
});
