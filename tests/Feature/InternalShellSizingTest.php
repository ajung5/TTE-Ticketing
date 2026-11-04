<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps the admin dashboard within the shared responsive shell with a collapsible desktop sidebar', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('class="shell tte-internal"', false)
        ->assertSee('data-sidebar-toggle', false)
        ->assertSee('aria-pressed="false"', false)
        ->assertSee('tte-internal-refresh.css')
        ->assertSee('tte-internal-refresh.js')
        ->assertSee('tte-ams-disclosure')
        ->assertSee('Statistik Verifikator');
});

it('uses the same shell for verifiers without changing ticket authorization', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $this->actingAs($verifier)->get(route('tickets.index'))
        ->assertOk()
        ->assertSee('data-sidebar-toggle', false)
        ->assertSee('data-menu-toggle', false)
        ->assertSee('Antrean Permohonan TTE');
    $this->get(route('admin.dashboard'))->assertForbidden();
});

it('ships responsive viewport and scroll containment rules with the internal stylesheet', function () {
    $css = file_get_contents(public_path('css/tte-internal-refresh.css'));
    $js = file_get_contents(public_path('js/tte-internal-refresh.js'));
    expect($css)->toContain('TTE v51: viewport-safe shell')
        ->toContain('.tte-internal .table-scroll')
        ->toContain('overflow-x: auto;')
        ->toContain('.tte-internal.tte-sidebar-collapsed')
        ->and($js)->toContain('tte-sidebar-collapsed-v1')
        ->toContain('data-sidebar-toggle');
});
