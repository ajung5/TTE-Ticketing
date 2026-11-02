<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function v18Ticket(User $verifier, string $service = 'Reset Passphrase'): Ticket
{
    return Ticket::create([
        'nama_user' => 'Pemohon AMS V18',
        'no_wa' => '6285812341234',
        'email' => 'pemohon@subang.go.id',
        'layanan' => $service,
        'status' => 'In Progress',
        'source' => 'direct',
        'claimed_by' => $verifier->id,
    ]);
}

it('requires explicit certificate issuance status despite verified WhatsApp', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = v18Ticket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'wa_verification_status' => 'verified',
            'certificate_status' => 'active', // Legacy input must not bypass new verification.
        ])
        ->assertSessionHasErrors('certificate_issuance_status');
    expect($ticket->fresh()->wa_checked_at)->toBeNull();
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
});

it(
    'continues issuance and blocks completion even with verified WhatsApp',
    function () {
        $owner = User::factory()->create(['role' => 'verifier']);
        $ticket = v18Ticket($owner);
        $this->actingAs($owner)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'wa_verification_status' => 'verified',
                'activation_status' => 'not_activated', // Ignored: not part of the verified-WA branch.
                'certificate_issuance_status' => 'not_issued',
            ])
            ->assertRedirect();
        expect($ticket->fresh()->activation_status)->toBeNull();
        expect($ticket->fresh()->certificate_status)->toBe('inactive');
        expect($ticket->fresh()->certificate_issuance_status)->toBe('not_issued');
        $this->actingAs($owner)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('SE Belum Terbit');
        $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
        $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
        $this->assertDatabaseHas('wa_notifications', [
            'ticket_id' => $ticket->id,
            'event_type' => 'certificate_not_issued',
        ]);
    },
);

it(
    'prevents a verified-WA Pendaftaran from being called a mismatch while SE is not issued',
    function () {
        $owner = User::factory()->create(['role' => 'verifier']);
        $ticket = v18Ticket($owner, 'Pendaftaran');
        $this->actingAs($owner)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'wa_verification_status' => 'verified',
                'certificate_issuance_status' => 'not_issued',
            ])
            ->assertRedirect();
        expect($ticket->fresh()->recommended_service)->toBeNull();
        $this->actingAs($owner)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('SE Belum Terbit');
        $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
    },
);

it('switches from SE Belum Terbit directly to ready for completion after issuance', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = v18Ticket($owner, 'Pendaftaran');
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'not_issued',
        ])
        ->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($owner)
        ->post(route('tickets.whatsapp.service.confirm', $ticket))
        ->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);

    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
        ])
        ->assertRedirect();
    expect($ticket->fresh()->certificate_status)->toBe('active');
    $this->actingAs($owner)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('SE Sudah Terbit — Siap Ditandai Selesai');
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Done');
});
