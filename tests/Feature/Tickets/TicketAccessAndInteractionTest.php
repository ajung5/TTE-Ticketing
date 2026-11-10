<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\WaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;

uses(RefreshDatabase::class);

// Source: Current/AuthorizationAndReportingTest.php
function rbReportingTicket(string $status = 'Open', array $extra = []): Ticket {
    return Ticket::create(
        array_merge(
            [
                'nama_user' => 'Pemohon Laporan',
                'no_wa' => '6281212345678',
                'email' => 'laporan@example.test',
                'layanan' => 'Reset Passphrase',
                'status' => $status,
                'source' => 'direct',
                'opd' => 'OPD Pengujian',
            ],
            $extra,
        ),
    );
}

it('requires authentication before displaying verifier ticket queue', function () {
    $this->get(route('tickets.index'))->assertRedirect();
});

it('allows only verifiers to claim a ticket and prevents double claim', function () {
    $first = User::factory()->create(['role' => 'verifier']);
    $second = User::factory()->create(['role' => 'verifier']);
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = rbReportingTicket();
    $this->actingAs($admin)->post(route('tickets.claim', $ticket))->assertForbidden();
    $this->actingAs($first)->post(route('tickets.claim', $ticket))->assertRedirect();
    $this->actingAs($second)->post(route('tickets.claim', $ticket))->assertStatus(409);
    expect($ticket->fresh()->claimed_by)
        ->toBe($first->id)
        ->and($ticket->fresh()->status)
        ->toBe('In Progress');
});

it('hides another verifier claimed ticket details', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $ticket = rbReportingTicket('In Progress', ['claimed_by' => $owner->id]);
    $this->actingAs($other)->get(route('tickets.show', $ticket))->assertForbidden();
});

it('permits release only before any ticket follow-up has been recorded', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = rbReportingTicket();
    $this->actingAs($owner)->post(route('tickets.claim', $ticket))->assertRedirect();
    $this->post(route('tickets.release', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Open')->and($ticket->fresh()->claimed_by)->toBeNull();
    $this->post(route('tickets.claim', $ticket))->assertRedirect();
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'not_activated',
    ])->assertSessionHasNoErrors();
    $this->post(route('tickets.release', $ticket))->assertStatus(409);
});

it('restricts AMS editing to assigned verifier and forbids admin operations', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = rbReportingTicket('In Progress', ['claimed_by' => $owner->id]);
    $request = ['activation_status' => 'not_activated'];
    $this->actingAs($other)
        ->post(route('tickets.verify-whatsapp', $ticket), $request)
        ->assertForbidden();
    $this->actingAs($admin)
        ->post(route('tickets.verify-whatsapp', $ticket), $request)
        ->assertForbidden();
    expect($ticket->fresh()->wa_checked_at)->toBeNull();
});

it(
    'shows revoked certificate aggregates to admins but denies verifier dashboard access',
    function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $verifier = User::factory()->create(['role' => 'verifier']);
        rbReportingTicket('In Progress', [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'revoked',
        ]);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('SE Revoke');
        $this->actingAs($verifier)->get(route('admin.dashboard'))->assertForbidden();
    },
);

it(
    'exports requested service and actual outcome as different fields without personal identifiers',
    function () {
        $admin = User::factory()->create(['role' => 'admin']);
        rbReportingTicket('Done', [
            'ticket_number' => 'RB-REPORT-01',
            'actual_process' => 'certificate_reissuance',
            'service_outcome' => 'certificate_reissued',
            'completed_at' => now(),
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'issued',
            'certificate_validity_status' => 'active',
        ]);
        $response = $this->actingAs($admin)->get(route('admin.reports.tickets.csv'));
        $response->assertOk();
        $csv = $response->streamedContent();
        expect($csv)
            ->toContain('Jenis Layanan')
            ->toContain('Proses Aktual')
            ->toContain('Hasil Pelayanan Aktual')
            ->toContain('RB-REPORT-01')
            ->toContain('Sertifikat Elektronik berhasil diterbitkan ulang')
            ->not->toContain('laporan@example.test')
            ->not->toContain('6281212345678');
    },
);

it('separates returned tickets from completed outcomes in admin reports', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    rbReportingTicket('Returned', ['recommended_service' => 'Pembaruan']);
    $this->actingAs($admin)
        ->get(route('admin.reports.tickets.index'))
        ->assertOk()
        ->assertSee('Perlu Pengajuan Ulang')
        ->assertSee('Selesai Selama Periode');
});

// Source: TteUxV613Test.php
function uxV613Ticket(string $name, string $status, ?int $owner, bool $waiting = false): Ticket {
    return Ticket::create([
        'nama_user' => $name,
        'no_wa' => '6281234567890',
        'layanan' => 'Pembaruan',
        'status' => $status,
        'source' => 'direct',
        'claimed_by' => $owner,
        'awaiting_applicant_action' => $waiting,
    ]);
}

it('limits waiting-applicant focus to tickets owned by the current verifier', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    uxV613Ticket('Pemohon Menunggu V613', 'In Progress', $verifier->id, true);
    uxV613Ticket('Pemohon Bisa Diproses V613', 'In Progress', $verifier->id);
    uxV613Ticket('Pemohon Petugas Lain V613', 'In Progress', $other->id, true);

    $this->actingAs($verifier)
        ->get(route('tickets.index', ['focus' => 'waiting']))
        ->assertOk()
        ->assertSee('Pemohon Menunggu V613')
        ->assertDontSee('Pemohon Bisa Diproses V613')
        ->assertDontSee('Pemohon Petugas Lain V613');
});

it('shows unclaimed and own actionable tickets without exposing others work', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    uxV613Ticket('Permohonan Baru V613', 'Open', null);
    uxV613Ticket('Permohonan Saya V613', 'In Progress', $verifier->id);
    uxV613Ticket('Permohonan Ditunggu V613', 'In Progress', $verifier->id, true);
    uxV613Ticket('Permohonan Orang Lain V613', 'In Progress', $other->id);

    $this->actingAs($verifier)
        ->get(route('tickets.index', ['focus' => 'action']))
        ->assertOk()
        ->assertSee('Permohonan Baru V613')
        ->assertSee('Permohonan Saya V613')
        ->assertDontSee('Permohonan Ditunggu V613')
        ->assertDontSee('Permohonan Orang Lain V613');
});

it('renders a period-labeled admin dashboard without permitting verifier access', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $this->actingAs($admin)
        ->get(route('admin.dashboard', ['period' => 'week']))
        ->assertOk()
        ->assertSee('Ringkasan Periode')
        ->assertSee('Selesai dalam periode')
        ->assertSee('Menunggu Pemohon Saat Ini');
    $this->actingAs($verifier)->get(route('admin.dashboard'))->assertForbidden();
});

it('exposes accessible help for a failed public NIK or NIP lookup', function () {
    $this->get(route('public.form'))
        ->assertOk()
        ->assertSee('id="email-help-trigger"', false)
        ->assertSee('email-help-modal');
    $js = file_get_contents(public_path('js/tte-request.js'));
    expect($js)->toContain("e.key === 'Tab'")->toContain('helpTrigger.hidden = false');
});

it('includes a copy-ticket action only when a new ticket has been created', function () {
    // Rendering a Blade view directly bypasses the web ShareErrorsFromSession middleware.
    $errors = new ViewErrorBag();
    $successful = view('public.success', [
        'activeRequestNotice' => false,
        'ticketNumber' => 'TTE-V613-DEMO',
        'errors' => $errors,
    ])->render();
    expect($successful)->toContain('data-tte-ticket-copy');
    $blocked = view('public.success', [
        'activeRequestNotice' => true,
        'ticketNumber' => null,
        'errors' => $errors,
    ])->render();
    expect($blocked)->not->toContain('data-tte-ticket-copy');
});

// Source: TteWorkflowUxV58Test.php
function ux58Ticket(
    User $owner,
    string $status = 'In Progress',
    string $service = 'Reset Passphrase',
): Ticket {
    return Ticket::create([
        'nama_user' => 'Pemohon Uji UX',
        'no_wa' => '6281212345678',
        'layanan' => $service,
        'source' => 'direct',
        'status' => $status,
        'claimed_by' => $owner->id,
        'ticket_number' => 'TTE-UX58-' . uniqid(),
    ]);
}

it('offers one contextual next action with a backend-decided destination', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = ux58Ticket($verifier);
    $this->actingAs($verifier)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Tindakan Berikutnya')
        ->assertSee('Periksa akun di AMS BSrE')
        ->assertSee('href="#tte-ams-check"', false)
        ->assertSee('id="tte-ticket-workflow"', false);
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'not_activated',
    ])->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertSee('Kirim notifikasi sesuai AMS');
});

it('does not cancel a valid service confirmation when the same message is reopened', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = ux58Ticket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'not_activated',
        ])
        ->assertSessionHasNoErrors();
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $before = WaNotification::where('ticket_id', $ticket->id)
        ->where('event_type', 'activation_required')
        ->firstOrFail();
    expect($before->confirmed_at)->not->toBeNull();
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    expect($before->fresh()->confirmed_at)->not->toBeNull();
});

it('invalidates a previous confirmation when the message content actually changes', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = ux58Ticket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'not_activated',
        ])
        ->assertSessionHasNoErrors();
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $ticket->update(['nama_user' => 'Nama Baru Uji']);
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $notice = WaNotification::where('ticket_id', $ticket->id)
        ->where('event_type', 'activation_required')
        ->firstOrFail();
    expect($notice->confirmed_at)->toBeNull();
});

it('keeps final notification confirmed on reopening the identical content', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = ux58Ticket($verifier, 'Done');
    $this->actingAs($verifier)->post(route('tickets.whatsapp', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.confirm', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp', $ticket))->assertRedirect();
    expect(
        WaNotification::where('ticket_id', $ticket->id)
            ->where('event_type', 'completed')
            ->firstOrFail()->confirmed_at,
    )->not->toBeNull();
});

it('prioritizes own tickets and oldest unclaimed tickets, without exposing others', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $own = ux58Ticket($verifier);
    $foreign = ux58Ticket($other);
    $openOld = ux58Ticket($verifier, 'Open');
    $openOld->update(['claimed_by' => null, 'created_at' => now()->subDays(2)]);
    $openNew = ux58Ticket($verifier, 'Open');
    $openNew->update(['claimed_by' => null, 'created_at' => now()->subDay()]);
    $this->actingAs($verifier)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertSee($own->ticket_number)
        ->assertDontSee($foreign->ticket_number)
        ->assertDontSee($openOld->ticket_number);
    $result = $this->get(route('tickets.index', ['status' => 'Open']))->assertOk();
    expect(strpos($result->getContent(), $openOld->ticket_number))->toBeLessThan(
        strpos($result->getContent(), $openNew->ticket_number),
    );
});

it('displays read-only system health to admin and protects admin/verifier separation', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Kesehatan Sistem TTE')
        ->assertSee('Direktori Email')
        ->assertSee('Status konfigurasi, bukan bukti email terkirim');
    $this->get(route('admin.verifiers.index'))->assertOk()->assertSee('Kirim Reset Email');
    $this->actingAs($verifier)->get(route('admin.dashboard'))->assertForbidden();
});

it('uses synthetic data preview rather than a real employee record in preset editor', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->get(route('admin.templates.index'))
        ->assertOk()
        ->assertSee('Pratinjau dengan data contoh')
        ->assertSee('tte-app.js')
        ->assertSee('data-tte-preset-preview');
    $js = file_get_contents(public_path('js/tte-app.js'));
    expect($js)->toContain('contoh@subang.go.id')->not->toContain('fetch(');
});
