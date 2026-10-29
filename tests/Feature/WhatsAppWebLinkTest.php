<?php

use App\Models\Ticket;
use App\Models\User;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses WhatsApp Web directly and encodes Indonesian message safely', function () {
    $ticket = new Ticket(['no_wa' => '6281212345678']);
    $url = app(WhatsAppMessage::class)->url($ticket, "Halo, pesan TTE\nSudah selesai & diverifikasi.");

    expect($url)->toStartWith('https://web.whatsapp.com/send?phone=6281212345678&text=');
    expect(rawurldecode(explode('&text=', $url, 2)[1]))->toBe("Halo, pesan TTE\nSudah selesai & diverifikasi.");
    expect($url)->not->toContain('wa.me');
});

it('opens follow-up and completion WhatsApp in the same dedicated browser target', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = Ticket::create([
        'nama_user' => 'Pemohon Demo',
        'no_wa' => '6281212345678',
        'layanan' => 'Reset Passphrase',
        'status' => 'In Progress',
        'wa_verification_status' => 'verified',
        'source' => 'demo',
        'claimed_by' => $verifier->id,
        'ticket_number' => 'TTE-TEST-000001',
    ]);

    $this->actingAs($verifier)->post(route('tickets.note', $ticket), [
        'note' => 'Pemohon telah diverifikasi dan sedang ditindaklanjuti.',
    ])->assertRedirect();

    $this->actingAs($verifier)->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('target="tte_whatsapp_web"', false);

    $this->actingAs($verifier)->post(route('tickets.complete', $ticket), [
        'resolution_note' => 'Penanganan selesai sesuai prosedur dan telah diverifikasi.',
    ])->assertRedirect();

    $this->actingAs($verifier)->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('target="tte_whatsapp_web"', false);
});
