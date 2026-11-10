<?php

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Models\WaNotification;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function v60Ticket(User $verifier, string $service): Ticket {
    return Ticket::create([
        'nama_user' => 'Pemohon Pengujian V60',
        'no_wa' => '6281212345678',
        'email' => 'contoh@example.test',
        'layanan' => $service,
        'source' => 'direct',
        'status' => 'In Progress',
        'claimed_by' => $verifier->id,
        'ticket_number' => 'TTE-V60-' . uniqid(),
    ]);
}

it(
    'finishes reset and renewal after confirmed activation notice and AMS recheck without an intermediate service notice',
    function (string $service) {
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = v60Ticket($verifier, $service);
        $originalNumber = $ticket->ticket_number;
        $this->actingAs($verifier)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'activation_status' => 'not_activated',
            ])
            ->assertSessionHasNoErrors();
        $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
        $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
        $this->assertDatabaseHas('wa_notifications', [
            'ticket_id' => $ticket->id,
            'event_type' => 'activation_required',
        ]);
        $this->post(route('tickets.complete', $ticket))->assertStatus(409);

        $this->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Siap Ditandai Selesai')
            ->assertSee('Tandai')
            ->assertDontSee('id="tte-service-notification"', false);
        expect($ticket->fresh()->actual_process)->toBe('certificate_issuance');
        $this->post(route('tickets.complete', $ticket))->assertRedirect();
        $done = $ticket->fresh();
        expect($done->status)
            ->toBe('Done')
            ->and($done->ticket_number)
            ->toBe($originalNumber)
            ->and($done->layanan)
            ->toBe($service)
            ->and($done->service_outcome)
            ->toBe('certificate_issued');
        expect(app(WhatsAppMessage::class)->forCompletedTicket($done))
            ->toContain('berhasil diterbitkan')
            ->toContain('survei');

        // Pesan akhir tetap perlu benar-benar dikirim dan dikonfirmasi manual.
        $this->post(route('tickets.whatsapp', $ticket))->assertRedirect();
        $notice = WaNotification::where('ticket_id', $ticket->id)
            ->where('event_type', 'completed')
            ->firstOrFail();
        expect($notice->opened_at)->not->toBeNull()->and($notice->confirmed_at)->toBeNull();
        $this->post(route('tickets.whatsapp.confirm', $ticket))->assertRedirect();
        expect($notice->fresh()->confirmed_at)->not->toBeNull();
    },
)->with(['Reset Passphrase', 'Pembaruan']);

it(
    'does not permit completion after activation without verified WA or active issued certificate',
    function () {
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = v60Ticket($verifier, 'Reset Passphrase');
        $this->actingAs($verifier)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'activation_status' => 'not_activated',
            ])
            ->assertSessionHasNoErrors();

        $this->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'unverified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
        ])->assertSessionHasNoErrors();
        $this->post(route('tickets.complete', $ticket))->assertStatus(409);

        $this->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'expired',
        ])->assertSessionHasNoErrors();
        $this->post(route('tickets.complete', $ticket))->assertStatus(409);
        expect($ticket->fresh()->status)->toBe('In Progress');
    },
);

it(
    'does not bypass the normal reset or renewal notification when no activation history exists',
    function (string $service) {
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = v60Ticket($verifier, $service);
        $this->actingAs($verifier)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'activation_status' => 'activated',
                'wa_verification_status' => 'verified',
                'certificate_issuance_status' => 'issued',
                'certificate_validity_status' => 'active',
            ])
            ->assertSessionHasNoErrors();
        $this->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('id="tte-service-notification"', false);
        expect(
            TicketActivity::where('ticket_id', $ticket->id)
                ->where('action', 'wa_service_confirmed')
                ->exists(),
        )->toBeFalse();
    },
)->with(['Reset Passphrase', 'Pembaruan']);

it(
    'renders copyright on the left and app version on the right for public, admin and verifier',
    function () {
        config()->set('tte.version', '3.2.1');
        $copyright = 'Copyright@DiskominfoSubang 2026-' . now('Asia/Jakarta')->year;
        $check = function ($response) use ($copyright) {
            $response
                ->assertOk()
                ->assertSee($copyright)
                ->assertSee('class="tte-site-footer"', false)
                ->assertSee('class="tte-site-footer-copy"', false)
                ->assertSee('class="tte-site-footer-version"', false)
                ->assertSee('v3.2.1')
                ->assertSee('tte-footer.css')
                ->assertSee('tte-footer.css?v=60.1');
            $html = $response->getContent();
            expect(strpos($html, $copyright))->toBeLessThan(strpos($html, 'v3.2.1'));
        };
        $check($this->get(route('public.form')));
        $admin = User::factory()->create(['role' => 'admin']);
        $check($this->actingAs($admin)->get(route('admin.dashboard')));
        $verifier = User::factory()->create(['role' => 'verifier']);
        $check($this->actingAs($verifier)->get(route('tickets.index')));
    },
);
