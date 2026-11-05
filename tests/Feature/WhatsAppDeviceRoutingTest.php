<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\WaNotification;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adaptiveWaTicket(User $owner, string $status = 'In Progress'): Ticket
{
    return Ticket::create([
        'nama_user' => 'Pemohon Pengujian WhatsApp',
        'no_wa' => '6281212345678',
        'layanan' => 'Reset Passphrase',
        'source' => 'direct',
        'status' => $status,
        'claimed_by' => $owner->id,
    ]);
}

it('builds the official mobile universal link without changing the desktop web link', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = adaptiveWaTicket($verifier);
    $whatsapp = app(WhatsAppMessage::class);
    $body = "Halo pemohon & tim, pesan percobaan\nbaris kedua";

    expect($whatsapp->url($ticket, $body))
        ->toBe('https://web.whatsapp.com/send?phone=6281212345678&text=' . rawurlencode($body));
    expect($whatsapp->mobileUrl($ticket, $body))
        ->toBe('https://wa.me/6281212345678?text=' . rawurlencode($body));
});

it('redirects a mobile service open to wa.me and preserves the manual confirmation requirement', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = adaptiveWaTicket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), ['activation_status' => 'not_activated'])
        ->assertSessionHasNoErrors();
    $message = app(WhatsAppMessage::class)->forServiceTicket($ticket->fresh(), 'activation_required');

    $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')
        ->post(route('tickets.whatsapp.service', $ticket))
        ->assertRedirect(app(WhatsAppMessage::class)->mobileUrl($ticket, $message));

    $notice = WaNotification::where('ticket_id', $ticket->id)
        ->where('event_type', 'activation_required')->firstOrFail();
    expect($notice->opened_at)->not->toBeNull();
    expect($notice->confirmed_at)->toBeNull();
});

it('retains WhatsApp Web on desktop and accepts an explicit mobile client hint for tablet browsers', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = adaptiveWaTicket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), ['activation_status' => 'not_activated'])
        ->assertSessionHasNoErrors();
    $message = app(WhatsAppMessage::class)->forServiceTicket($ticket->fresh(), 'activation_required');
    $web = app(WhatsAppMessage::class)->url($ticket, $message);
    $mobile = app(WhatsAppMessage::class)->mobileUrl($ticket, $message);

    $this->withHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64)')
        ->post(route('tickets.whatsapp.service', $ticket))
        ->assertRedirect($web);
    $this->withHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64)')
        ->post(route('tickets.whatsapp.service', $ticket), ['wa_client' => 'mobile'])
        ->assertRedirect($mobile);
    $this->withHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64)')
        ->postJson(route('tickets.whatsapp.service', $ticket))
        ->assertOk()->assertJsonPath('url', $web);
});

it('opens the final survey notification with a mobile universal link while retaining its manual state', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = adaptiveWaTicket($verifier, 'Done');
    $ticket->update(['ticket_number' => 'TTE-WA-ADAPT-01']);
    $message = app(WhatsAppMessage::class)->forCompletedTicket($ticket->fresh());
    $this->actingAs($verifier)
        ->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14; Pixel 7)')
        ->post(route('tickets.whatsapp', $ticket))
        ->assertRedirect(app(WhatsAppMessage::class)->mobileUrl($ticket, $message));
    $notice = WaNotification::where('ticket_id', $ticket->id)->where('event_type', 'completed')->firstOrFail();
    expect($notice->opened_at)->not->toBeNull();
    expect($notice->confirmed_at)->toBeNull();
});

it('keeps mobile navigation progressive with a native form fallback and accessible labels', function () {
    $js = file_get_contents(public_path('js/whatsapp-open-refresh.js'));
    expect($js)->toContain('wa_client')->toContain('pendingMobileReturn')
        ->toContain('visibilitychange')->toContain('document.addEventListener');
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = adaptiveWaTicket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), ['activation_status' => 'not_activated'])
        ->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('whatsapp-open-refresh.js')
        ->assertSee('Buka WhatsApp');
});
