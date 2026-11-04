<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\WaMessageTemplate;
use App\Models\WaNotification;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rbWhatsAppTicket(User $owner, string $service = 'Reset Passphrase', string $source = 'direct'): Ticket
{
    return Ticket::create([
        'nama_user' => 'Pemohon Notifikasi', 'no_wa' => '6281212345678',
        'email' => 'pesan@example.test', 'layanan' => $service,
        'status' => 'In Progress', 'source' => $source, 'claimed_by' => $owner->id,
    ]);
}

it('selects activation notice instead of an incorrect service correction', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'not_activated',
    ])->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertSee('Aktivasi Akun Diperlukan');
    $message = app(WhatsAppMessage::class)->forServiceTicket($ticket->fresh(), 'activation_required');
    expect($message)->toContain('tiket')->not->toContain('Perbaikan Jenis Permohonan');
});

it('informs pending issuance and unverified WA in the same notice', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated',
        'wa_verification_status' => 'unverified',
        'certificate_issuance_status' => 'not_issued',
    ])->assertSessionHasNoErrors();
    $message = app(WhatsAppMessage::class)->forServiceTicket($ticket->fresh(), 'certificate_not_issued');
    expect($message)->toContain('belum terverifikasi');
    $this->get(route('tickets.show', $ticket))->assertSee('SE Belum Terbit');
});

it('directs revoked certificates to reissuance, retaining the ticket', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated',
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued',
        'certificate_validity_status' => 'revoked',
    ])->assertSessionHasNoErrors();
    $message = app(WhatsAppMessage::class)->forServiceTicket($ticket->fresh(), 'certificate_revoked');
    expect($message)->toContain('Penerbitan Ulang');
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', [
        'ticket_id' => $ticket->id, 'event_type' => 'certificate_revoked',
    ]);
    expect($ticket->fresh()->status)->toBe('In Progress');
});

it('requires manual service notification confirmation after WhatsApp is opened', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated',
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'not_issued',
    ])->assertSessionHasNoErrors();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertStatus(409);
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $notice = WaNotification::where('ticket_id', $ticket->id)->where('event_type', 'certificate_not_issued')->firstOrFail();
    expect($notice->opened_at)->not->toBeNull()
        ->and($notice->confirmed_at)->toBeNull();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    expect($notice->fresh()->confirmed_at)->not->toBeNull();
});

it('invalidates a service confirmation when the AMS decision changes', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated',
        'wa_verification_status' => 'unverified',
        'certificate_issuance_status' => 'issued',
        'certificate_validity_status' => 'active',
    ])->assertSessionHasNoErrors();
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', ['ticket_id' => $ticket->id, 'event_type' => 'unverified']);
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated',
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued',
        'certificate_validity_status' => 'active',
    ])->assertSessionHasNoErrors();
    $notice = WaNotification::where('ticket_id', $ticket->id)->where('event_type', 'unverified')->firstOrFail();
    expect($notice->confirmed_at)->toBeNull()
        ->and($notice->opened_at)->toBeNull();
});

it('does not allow other verifiers or admins to send service notifications', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = rbWhatsAppTicket($owner);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'not_activated',
    ])->assertSessionHasNoErrors();
    $this->actingAs($other)->post(route('tickets.whatsapp.service', $ticket))->assertForbidden();
    $this->actingAs($admin)->post(route('tickets.whatsapp.service', $ticket))->assertForbidden();
    expect(WaNotification::where('ticket_id', $ticket->id)->count())->toBe(0);
});

it('never opens WhatsApp for a simulated ticket', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner, 'Reset Passphrase', 'simulated');
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'not_activated',
    ])->assertSessionHasNoErrors();
    $this->post(route('tickets.whatsapp.service', $ticket))->assertForbidden();
});

it('renders completed messages using actual certificate outcome, not original service', function (string $initialValidity, string $outcomeText) {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner);
    $inspection = [
        'activation_status' => 'activated',
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => $initialValidity === 'not_issued' ? 'not_issued' : 'issued',
    ];
    if ($initialValidity !== 'not_issued') {
        $inspection['certificate_validity_status'] = $initialValidity;
    }
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), $inspection)
        ->assertSessionHasNoErrors();
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated',
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued',
        'certificate_validity_status' => 'active',
    ])->assertSessionHasNoErrors();
    $this->post(route('tickets.complete', $ticket))->assertRedirect();
    $message = app(WhatsAppMessage::class)->forCompletedTicket($ticket->fresh());
    expect($message)->toContain($outcomeText)->not->toContain('Reset Passphrase TTE');
})->with([
    ['not_issued', 'berhasil diterbitkan'],
    ['revoked', 'berhasil diterbitkan ulang'],
]);

it('keeps current WA templates in sync without hardcoding a historical count', function () {
    expect(array_keys(WaMessageTemplate::EVENTS))->toEqualCanonicalizing(array_keys(WaMessageTemplate::TEMPLATE_SERVICE_CODES));
    foreach (WaMessageTemplate::TEMPLATE_SERVICE_CODES as $event => $serviceCode) {
        $this->assertDatabaseHas('wa_message_templates', [
            'event_type' => $event, 'service_code' => $serviceCode,
        ]);
    }
});
