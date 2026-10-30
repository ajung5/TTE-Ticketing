<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reportTicket(array $attributes = []): Ticket
{
    return Ticket::create(array_merge([
        'ticket_number' => 'TTE-REPORT-'.\Illuminate\Support\Str::random(12),
        'nama_user' => 'Pemohon Contoh',
        'no_wa' => '6281212345678',
        'layanan' => 'Reset Passphrase',
        'status' => 'Open',
        'source' => 'direct',
    ], $attributes));
}

it('requires login and admin role for ticket reports and CSV exports', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);

    $this->get(route('admin.reports.tickets.index'))->assertRedirect(route('login'));
    $this->get(route('admin.reports.tickets.csv'))->assertRedirect(route('login'));
    $this->actingAs($verifier)->get(route('admin.reports.tickets.index'))->assertForbidden();
    $this->actingAs($verifier)->get(route('admin.reports.tickets.csv'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.reports.tickets.index'))
        ->assertOk()->assertSee('Laporan Tiket TTE')->assertSee('Unduh CSV Lengkap');
});

it('filters inclusive calendar dates in WIB including last-minute records', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $during = reportTicket(['nama_user' => 'Masuk Dalam Periode']);
    $before = reportTicket(['nama_user' => 'Sebelum Periode']);
    $after = reportTicket(['nama_user' => 'Setelah Periode']);

    $during->forceFill(['created_at' => \Carbon\CarbonImmutable::parse('2026-10-31 23:59:00', 'Asia/Jakarta')->utc()])->save();
    $before->forceFill(['created_at' => \Carbon\CarbonImmutable::parse('2026-09-30 23:59:00', 'Asia/Jakarta')->utc()])->save();
    $after->forceFill(['created_at' => \Carbon\CarbonImmutable::parse('2026-11-01 00:00:00', 'Asia/Jakarta')->utc()])->save();

    $this->actingAs($admin)->get(route('admin.reports.tickets.index', [
        'mode' => 'month', 'month' => '2026-10',
    ]))->assertOk()->assertSee('Masuk Dalam Periode')
        ->assertDontSee('Sebelum Periode')->assertDontSee('Setelah Periode');

    $this->actingAs($admin)->get(route('admin.reports.tickets.index', [
        'mode' => 'custom', 'from' => '2026-10-31', 'to' => '2026-10-31',
    ]))->assertOk()->assertSee('Masuk Dalam Periode')
        ->assertDontSee('Sebelum Periode')->assertDontSee('Setelah Periode');
});

it('shows completion during period separately from tickets received during period', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = reportTicket(['status' => 'Done', 'completed_at' => '2026-10-15 03:00:00']);
    $ticket->forceFill(['created_at' => '2026-09-29 03:00:00'])->save();

    $this->actingAs($admin)->get(route('admin.reports.tickets.index', [
        'mode' => 'month', 'month' => '2026-10',
    ]))->assertOk()->assertSee('Selesai Selama Periode')->assertSee('1');
});

it('rejects invalid periods instead of silently returning all tickets', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.reports.tickets.index', [
        'mode' => 'custom', 'from' => '2026-10-20', 'to' => '2026-10-01',
    ]))->assertSessionHasErrors('to');
    $this->actingAs($admin)->get(route('admin.reports.tickets.index', [
        'mode' => 'month', 'month' => '2026-15',
    ]))->assertSessionHasErrors('month');
});

it('streams full CSV excluding NIK email and WhatsApp and neutralizes CSV formula injection', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = reportTicket(['nama_user' => '=FORMULA()', 'email' => 'private@example.test',
        'nik' => '3273010101010001', 'no_wa' => '6281298765432']);
    $ticket->forceFill(['created_at' => '2026-10-02 00:00:00'])->save();

    $response = $this->actingAs($admin)->get(route('admin.reports.tickets.csv', [
        'mode' => 'month', 'month' => '2026-10',
    ]));
    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $contents = $response->streamedContent();
    expect($contents)->toContain("'=FORMULA()")
        ->not->toContain('private@example.test')
        ->not->toContain('3273010101010001')
        ->not->toContain('6281298765432');
});

it('includes soft-deleted verifier names in historic report rows', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier', 'name' => 'Petugas Arsip']);
    reportTicket(['status' => 'Done', 'claimed_by' => $verifier->id]);
    $verifier->delete();

    $this->actingAs($admin)->get(route('admin.reports.tickets.index', [
        'mode' => 'month', 'month' => now('Asia/Jakarta')->format('Y-m'),
    ]))->assertOk()->assertSee('Petugas Arsip');
});
