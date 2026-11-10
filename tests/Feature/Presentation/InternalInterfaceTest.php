<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Source: FaviconAndEdgeSidebarTest.php
it('loads favicon concept A for admin, verifier and public form', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    foreach (
        [
            $this->actingAs($admin)->get(route('admin.dashboard')),
            $this->actingAs($verifier)->get(route('tickets.index')),
            $this->get(route('public.form')),
        ]
        as $response
    ) {
        $response
            ->assertOk()
            ->assertSee('favicon.svg')
            ->assertSee('favicon-32x32.png')
            ->assertSee('apple-touch-icon.png')
            ->assertSee('site.webmanifest');
    }
});

it('places icon-only collapse button on the outer edge of desktop sidebar', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('class="tte-sidebar-toggle tte-sidebar-edge-toggle"', false)
        ->assertSee('data-sidebar-toggle', false)
        ->assertSee('aria-pressed="false"', false)
        ->assertSee('favicon.svg')
        ->assertDontSee('class="tte-sidebar-toggle-label"', false);
    $css = file_get_contents(public_path('css/tte-app.css'));
    $js = file_get_contents(public_path('js/tte-app.js'));
    expect($css)
        ->toContain('TTE v53: centered outer-edge toggle')
        ->toContain('.sidebar > .tte-sidebar-edge-toggle')
        ->toContain('top: 50%;')
        ->toContain('transform: translateY(-50%);')
        ->and($js)
        ->toContain('tte-sidebar-collapsed-v1')
        ->toContain('aria-label');
});

it('ships a valid icon set including multiple sized ICO images', function () {
    $files = [
        'favicon.svg',
        'favicon.ico',
        'favicon-16x16.png',
        'favicon-32x32.png',
        'apple-touch-icon.png',
        'android-chrome-192x192.png',
        'android-chrome-512x512.png',
        'site.webmanifest',
    ];
    foreach ($files as $name) {
        expect(is_file(public_path($name)))->toBeTrue();
    }
    expect(file_get_contents(public_path('favicon.svg')))
        ->toContain('#14243B')
        ->toContain('#23856A');
    $ico = file_get_contents(public_path('favicon.ico'));
    expect(substr($ico, 0, 4))->toBe("\x00\x00\x01\x00");
});

// Source: InternalShellSizingTest.php
it(
    'keeps the admin dashboard within the shared responsive shell with a collapsible desktop sidebar',
    function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('class="shell tte-internal"', false)
            ->assertSee('data-sidebar-toggle', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('tte-app.css')
            ->assertSee('tte-app.js')
            ->assertSee('tte-ams-disclosure')
            ->assertSee('Statistik Verifikator');
    },
);

it('uses the same shell for verifiers without changing ticket authorization', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $this->actingAs($verifier)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertSee('data-sidebar-toggle', false)
        ->assertSee('data-menu-toggle', false)
        ->assertSee('Antrean Permohonan TTE');
    $this->get(route('admin.dashboard'))->assertForbidden();
});

it(
    'ships responsive viewport and scroll containment rules with the internal stylesheet',
    function () {
        $css = file_get_contents(public_path('css/tte-app.css'));
        $js = file_get_contents(public_path('js/tte-app.js'));
        expect($css)
            ->toContain('TTE v51: viewport-safe shell')
            ->toContain('.tte-internal .table-scroll')
            ->toContain('overflow-x: auto;')
            ->toContain('.tte-internal.tte-sidebar-collapsed')
            ->and($js)
            ->toContain('tte-sidebar-collapsed-v1')
            ->toContain('data-sidebar-toggle');
    },
);

// Source: InternalUiRefreshTest.php
it(
    'keeps verifier queue private while showing the new responsive navigation and status tabs',
    function () {
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
            ->assertSee('tte-app.css')
            ->assertSee('tte-queue-tabs')
            ->assertSee('Milik Saya')
            ->assertSee('Menu Navigasi')
            ->assertDontSee('TTE-UI-OTHER');
    },
);

it(
    'keeps ticket inspection and handling actions available after the mobile layout changes',
    function () {
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
        $this->actingAs($verifier)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('tte-ticket-context')
            ->assertSee('Pemeriksaan AMS')
            ->assertSee('Simpan Hasil Pemeriksaan AMS')
            ->assertSee('Penanganan Tiket')
            ->assertSee('tte-history-details');
    },
);

it('shows the revised admin presentation without giving ticket handling rights', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'verifier']);
    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('tte-ams-disclosure')
        ->assertSee('tte-outcome-disclosure')
        ->assertSee('Pendaftaran Diproses (Administratif)');
    $this->get(route('tickets.index'))->assertForbidden();
});

it('keeps admin management forms in responsive dialogs and presets editable', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $this->actingAs($admin)
        ->get(route('admin.verifiers.index'))
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
    $this->actingAs($admin)
        ->get(route('admin.reports.tickets.index'))
        ->assertOk()
        ->assertSee('tte-report-jumps')
        ->assertSee('Rincian Tiket Masuk')
        ->assertSee('Unduh CSV Lengkap');
    $this->get(route('admin.surveys.index'))->assertOk()->assertSee('tte-survey-response-table');
});

// Source: SidebarIconOnlyTest.php
it('uses an icon-only sidebar collapse control in the admin shell', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('class="tte-sidebar-head"', false)
        ->assertSee('class="tte-sidebar-toggle-icon"', false)
        ->assertSee('data-sidebar-toggle', false)
        ->assertSee('aria-label="Perkecil menu samping"', false)
        ->assertDontSee('class="tte-sidebar-toggle-label"', false)
        ->assertSee('tte-app.css')
        ->assertSee('tte-app.js');
});

it('preserves the same icon-only desktop control and mobile menu for verifiers', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $this->actingAs($verifier)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertSee('data-sidebar-toggle', false)
        ->assertSee('data-menu-toggle', false)
        ->assertSee('Antrean Permohonan TTE');
});

it('has an icon control and CSS for both expanded and minimized sidebar states', function () {
    $css = file_get_contents(public_path('css/tte-app.css'));
    $js = file_get_contents(public_path('js/tte-app.js'));
    expect($css)
        ->toContain('TTE v52: icon-only desktop sidebar header.')
        ->toContain('.tte-internal.tte-sidebar-collapsed .tte-sidebar-head')
        ->toContain('.tte-toggle-chevron')
        ->and($js)
        ->toContain('tte-sidebar-collapsed-v1')
        ->toContain('aria-label');
});

// Source: TteFourUxImprovementsV59Test.php
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
    $js = file_get_contents(public_path('js/tte-app.js'));
    // TTE v69: format-independent JS assertions (quote style and whitespace may vary).
    $this->assertMatchesRegularExpression(
        '~\bbutton\s*\.\s*closest\s*\(\s*[\x27\x22]\.tte-preset-detail[\x27\x22]\s*\)~',
        $js,
    );
    $this->assertMatchesRegularExpression('~\blocalEditor\s*\|\|\s*activeEditor\b~', $js);
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
            ->assertSee('tte-app.js');
        $js = file_get_contents(public_path('js/tte-app.js'));
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
