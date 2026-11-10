<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
