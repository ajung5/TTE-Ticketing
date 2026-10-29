<?php

use App\Models\Ticket;
use App\Models\User;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses WhatsApp Web directly and safely encodes the message', function () {
    $ticket = new Ticket(['no_wa' => '6281212345678']);
    $message = "Halo, pesan TTE\nSudah selesai & diverifikasi.";
    $url = app(WhatsAppMessage::class)->url($ticket, $message);
    expect($url)->toStartWith('https://web.whatsapp.com/send?phone=6281212345678&text=');
    expect(rawurldecode(explode('&text=', $url, 2)[1]))->toBe($message);
    expect($url)->not->toContain('wa.me');
});

it('opens service and completion notices with the same WhatsApp Web browser target', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = Ticket::create([
        'nama_user' => 'Pemohon Demo', 'no_wa' => '6281212345678',
        'layanan' => 'Reset Passphrase', 'status' => 'In Progress',
        'source' => 'demo', 'claimed_by' => $verifier->id,
        'ticket_number' => 'TTE-TEST-000001',
    ]);
    $this->actingAs($verifier)->post(route('tickets.verify-whatsapp', $ticket), [
        'certificate_status' => 'active', 'wa_verification_status' => 'verified',
    ])->assertRedirect();
    $this->actingAs($verifier)->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('target="tte_whatsapp_web"', false);
    $this->actingAs($verifier)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($verifier)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->actingAs($verifier)->post(route('tickets.complete', $ticket))->assertRedirect();
    $this->actingAs($verifier)->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('target="tte_whatsapp_web"', false);
});
