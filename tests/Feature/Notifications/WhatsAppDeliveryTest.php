<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\WaMessageTemplate;
use App\Models\WaNotification;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Source: Current/WhatsAppWorkflowTest.php
function rbWhatsAppTicket(
    User $owner,
    string $service = 'Reset Passphrase',
    string $source = 'direct',
): Ticket {
    return Ticket::create([
        'nama_user' => 'Pemohon Notifikasi',
        'no_wa' => '6281212345678',
        'email' => 'pesan@example.test',
        'layanan' => $service,
        'status' => 'In Progress',
        'source' => $source,
        'claimed_by' => $owner->id,
    ]);
}

it('selects activation notice instead of an incorrect service correction', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'not_activated',
        ])
        ->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertSee('Aktivasi Akun Diperlukan');
    $message = app(WhatsAppMessage::class)->forServiceTicket(
        $ticket->fresh(),
        'activation_required',
    );
    expect($message)->toContain('tiket')->not->toContain('Perbaikan Jenis Permohonan');
});

it(
    'finishes a registration only after confirming Notifikasi 4 and then permits the survey',
    function () {
        $owner = User::factory()->create(['role' => 'verifier']);
        $ticket = rbWhatsAppTicket($owner, 'Pendaftaran');
        $this->actingAs($owner)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'activation_status' => 'not_activated',
            ])
            ->assertSessionHasNoErrors();
        $message = app(WhatsAppMessage::class)->forServiceTicket($ticket->fresh(), 'registration');
        expect($message)
            ->toContain('sedang diproses')
            ->toContain('belum diaktivasi')
            ->toContain('Tidak perlu mengajukan tiket baru');
        $this->get(route('tickets.show', $ticket))->assertSee('Pendaftaran Diproses');
        $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
        $this->assertDatabaseHas('wa_notifications', [
            'ticket_id' => $ticket->id,
            'event_type' => 'registration',
        ]);
        // Sekadar membuka WhatsApp tidak sama dengan konfirmasi pengiriman.
        expect($ticket->fresh()->status)->toBe('In Progress');
        $this->post(route('tickets.complete', $ticket))->assertStatus(409);
        $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
        $record = $ticket->fresh();
        expect($record->status)
            ->toBe('Done')
            ->and($record->actual_process)
            ->toBe('registration_followup')
            ->and($record->service_outcome)
            ->toBe('registration_processed')
            ->and($record->certificate_issuance_status)
            ->toBeNull()
            ->and($record->activation_status)
            ->toBe('not_activated');
        $message = app(WhatsAppMessage::class)->forCompletedTicket($record);
        expect($message)
            ->toContain('Permohonan Penerbitan SE telah diproses')
            ->toContain('/survei/')
            ->not->toContain('berhasil diterbitkan');
        $this->get(route('tickets.show', $ticket))->assertSee('Notifikasi Selesai + Survei');
        $this->get(app(WhatsAppMessage::class)->surveyUrl($record))->assertOk();
        $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertStatus(409);
        $this->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
        ])->assertStatus(409);
        $this->post(route('tickets.whatsapp', $ticket))->assertRedirect();
        $this->assertDatabaseHas('wa_notifications', [
            'ticket_id' => $ticket->id,
            'event_type' => 'completed',
        ]);
    },
);

it('does not auto-close Reset or Pembaruan after confirming activation notice', function (
    string $service,
) {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner, $service);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'not_activated',
        ])
        ->assertSessionHasNoErrors();
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)
        ->toBe('In Progress')
        ->and($ticket->fresh()->service_outcome)
        ->toBeNull();
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
})->with(['Reset Passphrase', 'Pembaruan']);

it('informs pending issuance and unverified WA in the same notice', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'unverified',
            'certificate_issuance_status' => 'not_issued',
        ])
        ->assertSessionHasNoErrors();
    $message = app(WhatsAppMessage::class)->forServiceTicket(
        $ticket->fresh(),
        'certificate_not_issued',
    );
    expect($message)->toContain('belum terverifikasi');
    $this->get(route('tickets.show', $ticket))->assertSee('SE Belum Terbit');
});

it('directs revoked certificates to reissuance, retaining the ticket', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'revoked',
        ])
        ->assertSessionHasNoErrors();
    $message = app(WhatsAppMessage::class)->forServiceTicket(
        $ticket->fresh(),
        'certificate_revoked',
    );
    expect($message)->toContain('Penerbitan Ulang');
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', [
        'ticket_id' => $ticket->id,
        'event_type' => 'certificate_revoked',
    ]);
    expect($ticket->fresh()->status)->toBe('In Progress');
});

it('requires manual service notification confirmation after WhatsApp is opened', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'not_issued',
        ])
        ->assertSessionHasNoErrors();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertStatus(409);
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $notice = WaNotification::where('ticket_id', $ticket->id)
        ->where('event_type', 'certificate_not_issued')
        ->firstOrFail();
    expect($notice->opened_at)->not->toBeNull()->and($notice->confirmed_at)->toBeNull();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    expect($notice->fresh()->confirmed_at)->not->toBeNull();
});

it('invalidates a service confirmation when the AMS decision changes', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'unverified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
        ])
        ->assertSessionHasNoErrors();
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', [
        'ticket_id' => $ticket->id,
        'event_type' => 'unverified',
    ]);
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated',
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued',
        'certificate_validity_status' => 'active',
    ])->assertSessionHasNoErrors();
    $notice = WaNotification::where('ticket_id', $ticket->id)
        ->where('event_type', 'unverified')
        ->firstOrFail();
    expect($notice->confirmed_at)->toBeNull()->and($notice->opened_at)->toBeNull();
});

it('does not allow other verifiers or admins to send service notifications', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = rbWhatsAppTicket($owner);
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'not_activated',
        ])
        ->assertSessionHasNoErrors();
    $this->actingAs($other)->post(route('tickets.whatsapp.service', $ticket))->assertForbidden();
    $this->actingAs($admin)->post(route('tickets.whatsapp.service', $ticket))->assertForbidden();
    expect(WaNotification::where('ticket_id', $ticket->id)->count())->toBe(0);
});

it('never opens WhatsApp for a simulated ticket', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner, 'Reset Passphrase', 'simulated');
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'not_activated',
        ])
        ->assertSessionHasNoErrors();
    $this->post(route('tickets.whatsapp.service', $ticket))->assertForbidden();
});

it('renders completed messages using actual certificate outcome, not original service', function (
    string $initialValidity,
    string $outcomeText,
) {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbWhatsAppTicket($owner);
    $inspection = [
        'activation_status' => 'activated',
        'wa_verification_status' => 'verified',
        'certificate_issuance_status' =>
            $initialValidity === 'not_issued' ? 'not_issued' : 'issued',
    ];
    if ($initialValidity !== 'not_issued') {
        $inspection['certificate_validity_status'] = $initialValidity;
    }
    $this->actingAs($owner)
        ->post(route('tickets.verify-whatsapp', $ticket), $inspection)
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
})->with([['not_issued', 'berhasil diterbitkan'], ['revoked', 'berhasil diterbitkan ulang']]);

it('keeps current WA templates in sync without hardcoding a historical count', function () {
    expect(array_keys(WaMessageTemplate::EVENTS))->toEqualCanonicalizing(
        array_keys(WaMessageTemplate::TEMPLATE_SERVICE_CODES),
    );
    foreach (WaMessageTemplate::TEMPLATE_SERVICE_CODES as $event => $serviceCode) {
        $this->assertDatabaseHas('wa_message_templates', [
            'event_type' => $event,
            'service_code' => $serviceCode,
        ]);
    }
});

// Source: WhatsAppDeviceRoutingTest.php
function adaptiveWaTicket(User $owner, string $status = 'In Progress'): Ticket {
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

    expect($whatsapp->url($ticket, $body))->toBe(
        'https://web.whatsapp.com/send?phone=6281212345678&text=' . rawurlencode($body),
    );
    expect($whatsapp->mobileUrl($ticket, $body))->toBe(
        'https://wa.me/6281212345678?text=' . rawurlencode($body),
    );
});

it(
    'redirects a mobile service open to wa.me and preserves the manual confirmation requirement',
    function () {
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = adaptiveWaTicket($verifier);
        $this->actingAs($verifier)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'activation_status' => 'not_activated',
            ])
            ->assertSessionHasNoErrors();
        $message = app(WhatsAppMessage::class)->forServiceTicket(
            $ticket->fresh(),
            'activation_required',
        );

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')
            ->post(route('tickets.whatsapp.service', $ticket))
            ->assertRedirect(app(WhatsAppMessage::class)->mobileUrl($ticket, $message));

        $notice = WaNotification::where('ticket_id', $ticket->id)
            ->where('event_type', 'activation_required')
            ->firstOrFail();
        expect($notice->opened_at)->not->toBeNull();
        expect($notice->confirmed_at)->toBeNull();
    },
);

it(
    'retains WhatsApp Web on desktop and accepts an explicit mobile client hint for tablet browsers',
    function () {
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = adaptiveWaTicket($verifier);
        $this->actingAs($verifier)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'activation_status' => 'not_activated',
            ])
            ->assertSessionHasNoErrors();
        $message = app(WhatsAppMessage::class)->forServiceTicket(
            $ticket->fresh(),
            'activation_required',
        );
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
            ->assertOk()
            ->assertJsonPath('url', $web);
    },
);

it(
    'opens the final survey notification with a mobile universal link while retaining its manual state',
    function () {
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = adaptiveWaTicket($verifier, 'Done');
        $ticket->update(['ticket_number' => 'TTE-WA-ADAPT-01']);
        $message = app(WhatsAppMessage::class)->forCompletedTicket($ticket->fresh());
        $this->actingAs($verifier)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14; Pixel 7)')
            ->post(route('tickets.whatsapp', $ticket))
            ->assertRedirect(app(WhatsAppMessage::class)->mobileUrl($ticket, $message));
        $notice = WaNotification::where('ticket_id', $ticket->id)
            ->where('event_type', 'completed')
            ->firstOrFail();
        expect($notice->opened_at)->not->toBeNull();
        expect($notice->confirmed_at)->toBeNull();
    },
);

it(
    'keeps mobile navigation progressive with a native form fallback and accessible labels',
    function () {
        $js = file_get_contents(public_path('js/tte-ticket.js'));
        expect($js)
            ->toContain('wa_client')
            ->toContain('pendingMobileReturn')
            ->toContain('visibilitychange')
            ->toContain('document.addEventListener');
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = adaptiveWaTicket($verifier);
        $this->actingAs($verifier)
            ->post(route('tickets.verify-whatsapp', $ticket), [
                'activation_status' => 'not_activated',
            ])
            ->assertSessionHasNoErrors();
        $this->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('tte-ticket.js')
            ->assertSee('Buka WhatsApp');
    },
);

// Source: WhatsAppNativeMobileV57Test.php
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
    $js = file_get_contents(public_path('js/tte-ticket.js'));
    $css = file_get_contents(public_path('css/tte-ticket.css'));
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
        ->assertSee('tte-ticket.js')
        ->assertSee('tte-ticket.js')
        ->assertSee(\App\Support\VersionedAsset::url('js/tte-ticket.js'), false);
});
