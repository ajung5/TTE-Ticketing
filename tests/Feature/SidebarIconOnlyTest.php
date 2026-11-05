<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
        ->assertSee('tte-internal-refresh.css')
        ->assertSee('tte-internal-refresh.js');
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
    $css = file_get_contents(public_path('css/tte-internal-refresh.css'));
    $js = file_get_contents(public_path('js/tte-internal-refresh.js'));
    expect($css)
        ->toContain('TTE v52: icon-only desktop sidebar header.')
        ->toContain('.tte-internal.tte-sidebar-collapsed .tte-sidebar-head')
        ->toContain('.tte-toggle-chevron')
        ->and($js)
        ->toContain('tte-sidebar-collapsed-v1')
        ->toContain('aria-label');
});
