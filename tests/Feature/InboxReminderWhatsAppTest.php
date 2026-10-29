<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\WaMessageTemplate;
use App\Models\WaNotification;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function amsTicket(array $overrides = []): Ticket {
    return Ticket::create(array_merge([
        'nama_user' => 'Pemohon Pemeriksaan AMS',
        'no_wa' => '6281212345678', 'layanan' => 'Reset Passphrase',
        'status' => 'In Progress', 'source' => 'direct',
        'ticket_number' => 'TTE-AMS-0001',
    ], $overrides));
}

it('registers six active message templates, retaining legacy templates as historical data', function () {
    foreach (WaMessageTemplate::TEMPLATE_SERVICE_CODES as $event => $service) {
        $this->assertDatabaseHas('wa_message_templates', ['event_type' => $event, 'service_code' => $service]);
    }
    expect(WaMessageTemplate::EVENTS)->toHaveCount(6);
});

it('prevents sending or completion before both AMS results exist', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsTicket(['claimed_by' => $owner->id]);
    $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertStatus(409);
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified',
    ])->assertSessionHasErrors('certificate_status');
    $this->actingAs($owner)->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('Verifikasi Akun TTE dan Nomor WA')->assertDontSee('Hasil penyelesaian');
});

it('uses certificate status before WA and separates reset, renewal, registration notifications', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    foreach ([
        ['Reset Passphrase','active','verified','reset'],
        ['Pembaruan','active','verified','renewal'],
        ['Pendaftaran','active','verified','registration'],
        ['Reset Passphrase','active','unverified','unverified'],
        ['Pendaftaran','inactive','verified','unregistered'],
        ['Pembaruan','inactive','unverified','unregistered'],
    ] as $i => [$layanan,$certificate,$wa,$expected]) {
        $ticket = amsTicket(['claimed_by' => $owner->id, 'layanan' => $layanan, 'ticket_number' => 'TTE-AMS-'.($i+1)]);
        $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
            'certificate_status' => $certificate, 'wa_verification_status' => $wa,
        ])->assertRedirect();
        $this->actingAs($owner)->get(route('tickets.show', $ticket))
            ->assertOk()->assertSee(WaMessageTemplate::EVENTS[$expected]);
        $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
        $this->assertDatabaseHas('wa_notifications', ['ticket_id' => $ticket->id, 'event_type' => $expected]);
    }
});

it('requires manual notification confirmation then allows completion without notes and adds survey link', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsTicket(['claimed_by' => $owner->id]);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'certificate_status' => 'active', 'wa_verification_status' => 'verified',
    ])->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertStatus(409);
    $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    expect(WaNotification::where('ticket_id',$ticket->id)->where('event_type','reset')->first()->confirmed_at)->not->toBeNull();
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Done');
    expect($ticket->fresh()->resolution_note)->toBeNull();
    $this->actingAs($owner)->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('Notifikasi Selesai + Survei')->assertSee('completed-wa-message');
    $message = app(WhatsAppMessage::class)->forCompletedTicket($ticket);
    expect($message)->toContain('/survei/'.$ticket->id)->toContain('signature=');
    $this->actingAs($owner)->post(route('tickets.whatsapp', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.confirm', $ticket))->assertRedirect();
});

it('blocks other verifiers and simulated WhatsApp messaging', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $stranger = User::factory()->create(['role' => 'verifier']);
    $ticket = amsTicket(['claimed_by' => $owner->id, 'source' => 'simulated']);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'certificate_status' => 'inactive', 'wa_verification_status' => 'unverified',
    ])->assertRedirect();
    $this->actingAs($stranger)->post(route('tickets.whatsapp.service', $ticket))->assertForbidden();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertForbidden();
    $this->actingAs($owner)->get(route('tickets.show', $ticket))
        ->assertSee('User Belum Terdaftar')->assertDontSee('Buka WhatsApp Web');
});

it('revokes old confirmation after AMS status changes', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsTicket(['claimed_by' => $owner->id]);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'certificate_status' => 'active', 'wa_verification_status' => 'verified',
    ])->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'certificate_status' => 'active', 'wa_verification_status' => 'unverified',
    ])->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->assertDatabaseHas('wa_notifications', ['ticket_id' => $ticket->id, 'event_type' => 'reset', 'confirmed_at' => null]);
});
