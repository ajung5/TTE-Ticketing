<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
    $css = file_get_contents(public_path('css/tte-internal-refresh.css'));
    $js = file_get_contents(public_path('js/tte-internal-refresh.js'));
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
