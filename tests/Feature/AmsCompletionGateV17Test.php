<?php

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Models\WaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function v17Ticket(User $owner, string $service = 'Reset Passphrase'): Ticket
{
    return Ticket::create([
        'nama_user' => 'Pemohon Gate AMS V17',
        'no_wa' => '6285812341234',
        'email' => 'pemohon@subang.go.id',
        'layanan' => $service,
        'status' => 'In Progress',
        'source' => 'direct',
        'claimed_by' => $owner->id,
    ]);
}

function v17CompletionButtonAttributes(string $html, Ticket $ticket): string
{
    $formAction = preg_quote(route('tickets.complete', $ticket), '~');
    preg_match('~<form method="post" action="'.$formAction.'"[^>]*>.*?<button\b([^>]*)>Tandai Selesai~s', $html, $matches);
    expect($matches)->not->toBeEmpty();

    return $matches[1];
}

it('enables completion as soon as the Reset Passphrase service notification is available', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = v17Ticket($owner);

    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified',
    ])->assertRedirect();

    $html = $this->actingAs($owner)->get(route('tickets.show', $ticket))
        ->assertOk()->getContent();
    expect(v17CompletionButtonAttributes($html, $ticket))->not->toContain('disabled');
    expect(WaNotification::where('ticket_id', $ticket->id)->count())->toBe(0);

    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Done');
    expect(TicketActivity::where('ticket_id', $ticket->id)
        ->where('action', 'completed')->firstOrFail()->note)
        ->toContain('Konfirmasi pengiriman notifikasi layanan belum tercatat');
});

it('keeps completion disabled after the WA-verification notice, even when that notice was confirmed', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = v17Ticket($owner, 'Pembaruan');

    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'unverified',
        'activation_status' => 'activated',
        'certificate_issuance_status' => 'issued',
    ])->assertRedirect();

    $html = $this->actingAs($owner)->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('Nomor WA Belum Terverifikasi')->getContent();
    expect(v17CompletionButtonAttributes($html, $ticket))->toContain('disabled');
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);

    $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);

    // Pemohon sudah melakukan verifikasi nomor WA; pemeriksaan AMS diulang.
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified',
    ])->assertRedirect();
    $html = $this->actingAs($owner)->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('Pembaruan Sertifikat')->getContent();
    expect(v17CompletionButtonAttributes($html, $ticket))->not->toContain('disabled');
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Done');
});

it('does not unlock completion for an unissued certificate', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = v17Ticket($owner);

    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'unverified',
        'activation_status' => 'activated',
        'certificate_issuance_status' => 'not_issued',
    ])->assertRedirect();
    $html = $this->actingAs($owner)->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('SE Belum Terbit')->getContent();
    expect(v17CompletionButtonAttributes($html, $ticket))->toContain('disabled');
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
});
