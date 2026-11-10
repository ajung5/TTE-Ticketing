<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\WaMessageTemplate;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rbTemplateTicket(User $verifier): Ticket {
    return Ticket::create([
        'nama_user' => 'Pemohon Template',
        'no_wa' => '6281212345678',
        'email' => 'template@example.test',
        'nik' => '3276010101010001',
        'layanan' => 'Reset Passphrase',
        'status' => 'In Progress',
        'source' => 'direct',
        'claimed_by' => $verifier->id,
    ]);
}

it('allows an administrator but not a verifier to manage active WhatsApp templates', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $template = WaMessageTemplate::where('event_type', 'certificate_revoked')
        ->where('service_code', '*')
        ->firstOrFail();
    $body =
        'Kepada {nama_pemohon}, tiket {nomor_tiket} berstatus Revoke. Petugas {nama_verifikator} meminta Penerbitan Ulang sesuai prosedur.';
    $this->actingAs($verifier)
        ->put(route('admin.templates.update', $template), ['body' => $body])
        ->assertForbidden();
    $this->actingAs($admin)
        ->put(route('admin.templates.update', $template), ['body' => $body])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    expect($template->fresh()->body)->toBe($body);
});

it('rejects unknown placeholders rather than rendering untrusted template variables', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $template = WaMessageTemplate::where('event_type', 'completed')
        ->where('service_code', '*')
        ->firstOrFail();
    $this->actingAs($admin)
        ->put(route('admin.templates.update', $template), [
            'body' =>
                'Yth {nama_pemohon}, tiket {nomor_tiket}, petugas {nama_verifikator}. Nilai rahasia {secret_token} tidak boleh tersedia.',
        ])
        ->assertSessionHasErrors('body');
});

it('keeps NIK masked when a template explicitly uses the NIK placeholder', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = rbTemplateTicket($verifier);
    WaMessageTemplate::where('event_type', 'reset')
        ->where('service_code', 'Reset Passphrase')
        ->firstOrFail()
        ->update([
            'body' =>
                'Yth {nama_pemohon}, NIK {nik} pada tiket {nomor_tiket}. Petugas: {nama_verifikator}.',
        ]);
    $message = app(WhatsAppMessage::class)->forServiceTicket($ticket, 'reset');
    expect($message)->toContain('0001')->not->toContain('3276010101010001');
});

it('uses the same WhatsApp Web browser target for service and completion messages', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = rbTemplateTicket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
        ])
        ->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertSee('target="tte_whatsapp_web"', false);
    $this->post(route('tickets.complete', $ticket))->assertRedirect();
    $this->get(route('tickets.show', $ticket))->assertSee('target="tte_whatsapp_web"', false);
});

it(
    'keeps old completed tickets readable when actual service outcome is still unknown',
    function () {
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = rbTemplateTicket($verifier);
        $ticket->update(['status' => 'Done', 'completed_at' => now(), 'service_outcome' => null]);
        $message = app(WhatsAppMessage::class)->forCompletedTicket($ticket->fresh());
        expect($message)->toContain('Reset Passphrase')->toContain('/survei/');
    },
);
