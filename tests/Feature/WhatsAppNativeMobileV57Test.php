<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\WaNotification;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function nativeV57Ticket(User $owner, string $status = 'In Progress'): Ticket {
    return Ticket::create([
        'nama_user' => 'Pengujian Native WhatsApp',
        'no_wa' => '6281212345678',
        'layanan' => 'Reset Passphrase',
        'source' => 'direct',
        'status' => $status,
        'claimed_by' => $owner->id,
    ]);
}

it(
    'records an authenticated mobile service open before returning a native-link handoff URL',
    function () {
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = nativeV57Ticket($verifier);
        $this->actingAs($verifier)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'activation_status' => 'not_activated',
            ])
            ->assertSessionHasNoErrors();
        $message = app(WhatsAppMessage::class)->forServiceTicket(
            $ticket->fresh(),
            'activation_required',
        );
        $this->postJson(route('tickets.whatsapp.service', $ticket), ['wa_client' => 'mobile'])
            ->assertOk()
            ->assertJsonPath('url', app(WhatsAppMessage::class)->mobileUrl($ticket, $message));
        $notice = WaNotification::where('ticket_id', $ticket->id)
            ->where('event_type', 'activation_required')
            ->firstOrFail();
        expect($notice->opened_at)->not->toBeNull();
        expect($notice->confirmed_at)->toBeNull();
    },
);

it('records final mobile notification but does not mark it sent by the user', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = nativeV57Ticket($verifier, 'Done');
    $ticket->update(['ticket_number' => 'TTE-V57-0001']);
    $message = app(WhatsAppMessage::class)->forCompletedTicket($ticket->fresh());
    $this->actingAs($verifier)
        ->postJson(route('tickets.whatsapp', $ticket), ['wa_client' => 'mobile'])
        ->assertOk()
        ->assertJsonPath('url', app(WhatsAppMessage::class)->mobileUrl($ticket, $message));
    $notice = WaNotification::where('ticket_id', $ticket->id)
        ->where('event_type', 'completed')
        ->firstOrFail();
    expect($notice->opened_at)->not->toBeNull();
    expect($notice->confirmed_at)->toBeNull();
});

it('provides direct native deep link and visible fallback after AJAX persistence', function () {
    $js = file_get_contents(public_path('js/whatsapp-open-refresh.js'));
    $css = file_get_contents(public_path('css/ams-workflow.css'));
    expect($js)
        ->toContain('TTE v57: native WhatsApp')
        ->toContain('whatsapp://send?phone=')
        ->toContain('Buka Aplikasi WhatsApp')
        ->toContain('Buka melalui browser')
        ->toContain('data.set(\'wa_client\', \'mobile\')')
        ->toContain('pendingMobileReturn')
        ->toContain('visibilitychange')
        ->toContain('event.preventDefault()')
        ->toContain('document.createElement(\'a\')')
        ->and($css)
        ->toContain('TTE v57: mobile native WhatsApp handoff');
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = nativeV57Ticket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'not_activated',
        ])
        ->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('whatsapp-open-refresh.js')
        ->assertSee('whatsapp-open-refresh.js')
        ->assertSee('?v=57');
});
