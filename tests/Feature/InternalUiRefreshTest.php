<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps verifier queue private while showing the new responsive navigation and status tabs', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    Ticket::create([
        'nama_user' => 'Tiket Orang Lain',
        'no_wa' => '6281212345678',
        'layanan' => 'Pendaftaran',
        'status' => 'In Progress',
        'claimed_by' => $other->id,
        'source' => 'direct',
        'ticket_number' => 'TTE-UI-OTHER',
    ]);
    $this->actingAs($verifier)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertSee('tte-internal-refresh.css')
        ->assertSee('tte-queue-tabs')
        ->assertSee('Milik Saya')
        ->assertSee('Menu Navigasi')
        ->assertDontSee('TTE-UI-OTHER');
});

it('keeps ticket inspection and handling actions available after the mobile layout changes', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = Ticket::create([
        'nama_user' => 'Contoh Pemohon',
        'no_wa' => '6281212345678',
        'layanan' => 'Pendaftaran',
        'status' => 'In Progress',
        'claimed_by' => $verifier->id,
        'source' => 'direct',
        'ticket_number' => 'TTE-UI-OWN',
    ]);
    $this->actingAs($verifier)->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('tte-ticket-context')
        ->assertSee('Pemeriksaan AMS')
        ->assertSee('Simpan Hasil Pemeriksaan AMS')
        ->assertSee('Penanganan Tiket')
        ->assertSee('tte-history-details');
});

it('shows the revised admin presentation without giving ticket handling rights', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'verifier']);
    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('tte-ams-disclosure')
        ->assertSee('tte-outcome-disclosure')
        ->assertSee('Pendaftaran Diproses (Administratif)');
    $this->get(route('tickets.index'))->assertForbidden();
});

it('keeps admin management forms in responsive dialogs and presets editable', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $this->actingAs($admin)->get(route('admin.verifiers.index'))
        ->assertOk()
        ->assertSee('tte-edit-verifier-' . $verifier->id)
        ->assertSee('Nonaktifkan')
        ->assertSee('Simpan Perubahan');
    $this->get(route('admin.templates.index'))
        ->assertOk()
        ->assertSee('tte-preset-detail')
        ->assertSee('Simpan Preset');
});

it('preserves admin reports, CSV filtering, and survey access after UI refresh', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.reports.tickets.index'))
        ->assertOk()
        ->assertSee('tte-report-jumps')
        ->assertSee('Rincian Tiket Masuk')
        ->assertSee('Unduh CSV Lengkap');
    $this->get(route('admin.surveys.index'))
        ->assertOk()
        ->assertSee('tte-survey-response-table');
});
