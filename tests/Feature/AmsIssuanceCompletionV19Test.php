<?php

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Models\WaNotification;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function v19Ticket(User $owner, string $service): Ticket {
    return Ticket::create([
        'nama_user' => 'Pemohon Uji V19',
        'no_wa' => '6281212345678',
        'email' => 'pemohon@subang.go.id',
        'layanan' => $service,
        'status' => 'In Progress',
        'source' => 'direct',
        'claimed_by' => $owner->id
    ]);
}

it('shows SE Belum Terbit first and then skips straight to Selesai + Survei after issuance', function () {
    foreach (['Pendaftaran'] as $service) {
        $owner = User::factory()->create(['role' => 'verifier']);
        $ticket = v19Ticket($owner, $service);
        $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'not_issued',
        ])->assertRedirect();
        $this->actingAs($owner)->get(route('tickets.show', $ticket))
            ->assertOk()->assertSee('SE Belum Terbit');
        $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
        $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
        $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
        $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);

        $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
        ])->assertRedirect();
        $this->actingAs($owner)->get(route('tickets.show', $ticket))
            ->assertOk()->assertSee('SE Sudah Terbit — Siap Ditandai Selesai')
            ->assertDontSee('id="service-wa-message"', false);
        $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertStatus(409);
        $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertStatus(409);
        $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertRedirect();
        expect($ticket->fresh()->status)->toBe('Done');
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'completed',
        ]);
        $this->actingAs($owner)->get(route('tickets.show', $ticket))
            ->assertOk()->assertSee('Notifikasi Selesai + Survei')
            ->assertSee('completed-wa-message');
        $message = app(WhatsAppMessage::class)->forCompletedTicket($ticket->fresh());
        expect($message)->toContain('/survei/'.$ticket->id)->toContain('signature=');
        expect(WaNotification::where('ticket_id',$ticket->id)
            ->whereIn('event_type', ['reset','renewal','registration'])->count())->toBe(0);
    }
});

it('does not skip ordinary service notification without confirmation of SE Belum Terbit', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = v19Ticket($owner, 'Pembaruan');
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'not_issued',
    ])->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    // Belum ada konfirmasi 'Saya Sudah Mengirim Pesan'.
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued',
    ])->assertRedirect();
    $this->actingAs($owner)->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('Pembaruan Sertifikat')
        ->assertDontSee('SE Sudah Terbit — Siap Ditandai Selesai');
});

it('keeps completion locked while certificate has not been issued', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = v19Ticket($owner, 'Reset Passphrase');
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'not_issued',
    ])->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->actingAs($owner)->get(route('tickets.show', $ticket))
        ->assertOk()->assertDontSee('SE Sudah Terbit — Siap Ditandai Selesai');
});
