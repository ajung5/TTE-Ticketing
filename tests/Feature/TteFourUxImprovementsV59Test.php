<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps a local placeholder guide available at the last WhatsApp preset editor', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $response = $this->actingAs($admin)->get(route('admin.templates.index'))->assertOk();
    $html = $response->getContent();
    expect(substr_count($html, 'class="tte-preset-inline-guide"'))->toBe(
        count(\App\Models\WaMessageTemplate::EVENTS),
    );
    expect($html)
        ->toContain('Semua Placeholder dan Panduan Penulisan')
        ->toContain('data-insert-token="{nama_pemohon}"')
        ->toContain('data-insert-token="{nomor_tiket}"');
    $js = file_get_contents(public_path('js/tte.js'));
    expect($js)
        ->toContain("button.closest('.tte-preset-detail')")
        ->toContain('localEditor || activeEditor');
});

it(
    'includes an accessible back-to-top control on authenticated pages with mobile-only behavior',
    function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-tte-back-top', false)
            ->assertSee('Kembali ke atas')
            ->assertSee('tte-back-to-top.js');
        $js = file_get_contents(public_path('js/tte-back-to-top.js'));
        expect($js)
            ->toContain('max-width: 680px')
            ->toContain('nearBottom')
            ->toContain('scrollTo({ top: 0')
            ->toContain('prefers-reduced-motion: reduce');
    },
);

it(
    'shows only Sudah Terbit / Belum Terbit issuance choices and highlights submitted service near AMS',
    function () {
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = Ticket::create([
            'nama_user' => 'Pemohon V59',
            'no_wa' => '6281212345678',
            'layanan' => 'Pembaruan',
            'source' => 'direct',
            'status' => 'In Progress',
            'claimed_by' => $verifier->id,
            'ticket_number' => 'TTE-UX59-0001',
        ]);
        $this->actingAs($verifier)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('LAYANAN DIAJUKAN OLEH PEMOHON')
            ->assertSee('tte-ams-requested-service')
            ->assertSee('Status penerbitan Sertifikat Elektronik')
            ->assertSee('Sudah Terbit')
            ->assertSee('Belum Terbit')
            ->assertDontSee('Sudah/pernah terbit')
            ->assertDontSee('Belum pernah terbit');
        $this->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'activated',
            'wa_verification_status' => 'verified',
            'certificate_issuance_status' => 'not_issued',
        ])->assertSessionHasNoErrors();
        expect($ticket->fresh()->certificate_issuance_status)->toBe('not_issued');
    },
);

it('does not give an administrator access to verifier-only AMS decisions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = Ticket::create([
        'nama_user' => 'Pemohon V59',
        'no_wa' => '6281212345678',
        'layanan' => 'Pendaftaran',
        'source' => 'direct',
        'status' => 'In Progress',
        'claimed_by' => $verifier->id,
    ]);
    $this->actingAs($admin)->get(route('tickets.show', $ticket))->assertForbidden();
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated',
    ])->assertForbidden();
});
