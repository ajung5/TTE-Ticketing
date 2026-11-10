<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;

uses(RefreshDatabase::class);

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
    $js = file_get_contents(public_path('js/public-request.js'));
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
