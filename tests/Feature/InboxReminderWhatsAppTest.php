<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\WaMessageTemplate;
use App\Models\WaNotification;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds inbox reminder presets for all three services', function () {
    foreach (WaMessageTemplate::SERVICES as $service => $label) {
        $template = WaMessageTemplate::where('service_code', $service)
            ->where('event_type', 'inbox_reminder')->firstOrFail();
        expect($template->body)->toContain('{nama_pemohon}', '{nomor_tiket}', '{email}', '{nama_verifikator}');
    }
    expect(WaMessageTemplate::count())->toBe(12);
});

it('requires the inbox reminder to be confirmed before opening or confirming completion notice', function () {
    $verifier = User::factory()->create(['role' => 'verifier', 'name' => 'Petugas Test']);
    $ticket = Ticket::create([
        'nama_user' => 'Pemohon Test', 'email' => 'pemohon@subang.go.id',
        'no_wa' => '6281212345678', 'layanan' => 'Pembaruan',
        'status' => 'Done', 'source' => 'demo', 'wa_verification_status' => 'verified',
        'claimed_by' => $verifier->id, 'ticket_number' => 'TTE-TEST-INBOX',
    ]);

    $this->actingAs($verifier)->post(route('tickets.whatsapp', $ticket))->assertStatus(409);
    $this->actingAs($verifier)->post(route('tickets.whatsapp.confirm', $ticket))->assertStatus(409);
    $this->actingAs($verifier)->post(route('tickets.whatsapp.inbox.confirm', $ticket))->assertStatus(409);
    $this->actingAs($verifier)->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('Pengingat Cek Inbox Email')
        ->assertSee('inbox-reminder-wa-message')->assertSee('Salin Pesan')
        ->assertSee('Sebelum mengirim notifikasi selesai');

    $this->actingAs($verifier)->post(route('tickets.whatsapp.inbox', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', ['ticket_id' => $ticket->id, 'event_type' => 'inbox_reminder', 'confirmed_at' => null]);
    $this->actingAs($verifier)->post(route('tickets.whatsapp', $ticket))->assertStatus(409);
    $this->actingAs($verifier)->post(route('tickets.whatsapp.inbox.confirm', $ticket))->assertRedirect();
    expect(WaNotification::where('ticket_id', $ticket->id)->where('event_type', 'inbox_reminder')->first()->confirmed_at)->not->toBeNull();
    $this->actingAs($verifier)->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('Buka WhatsApp Selesai');
    $this->actingAs($verifier)->post(route('tickets.whatsapp', $ticket))->assertRedirect();
    $this->actingAs($verifier)->post(route('tickets.whatsapp.confirm', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', ['ticket_id' => $ticket->id, 'event_type' => 'completed']);
    $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'wa_inbox_confirmed']);
});

it('blocks simulated and other verifier inbox reminders and renders the email variable', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $stranger = User::factory()->create(['role' => 'verifier']);
    $simulated = Ticket::create([
        'nama_user' => 'Data Simulasi', 'email' => 'simulasi@subang.go.id',
        'no_wa' => '6281212345678', 'layanan' => 'Reset Passphrase',
        'status' => 'Done', 'source' => 'simulated', 'wa_verification_status' => 'verified',
        'claimed_by' => $owner->id,
    ]);
    $this->actingAs($owner)->post(route('tickets.whatsapp.inbox', $simulated))->assertForbidden();
    $this->actingAs($owner)->post(route('tickets.whatsapp.inbox.confirm', $simulated))->assertForbidden();
    $this->actingAs($stranger)->post(route('tickets.whatsapp.inbox', $simulated))->assertForbidden();
    $this->actingAs($owner)->get(route('tickets.show', $simulated))
        ->assertOk()->assertSee('inbox-reminder-wa-message')->assertDontSee('Buka WhatsApp Pengingat Inbox');
    $simulated->load('claimant');
    expect(app(WhatsAppMessage::class)->forInboxReminderTicket($simulated))->toContain('simulasi@subang.go.id');
});
