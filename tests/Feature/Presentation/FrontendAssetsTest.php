<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('consolidates published assets into four CSS and three JavaScript files', function () {
    $cssFiles = glob(public_path('css/*.css'));
    $jsFiles = glob(public_path('js/*.js'));
    expect(count($cssFiles))->toBe(4);
    expect(count($jsFiles))->toBe(3);

    foreach (
        [
            'css/tte-app.css',
            'css/tte-request.css',
            'css/tte-ticket.css',
            'css/tte-survey.css',
            'js/tte-app.js',
            'js/tte-request.js',
            'js/tte-ticket.js',
        ]
        as $asset
    ) {
        expect(is_file(public_path($asset)))->toBeTrue();
    }
});

it('loads only necessary page bundles without losing the public request workflow', function () {
    $this->get(route('public.form'))
        ->assertOk()
        ->assertSee(\App\Support\VersionedAsset::url('css/tte-app.css'), false)
        ->assertSee(\App\Support\VersionedAsset::url('css/tte-request.css'), false)
        ->assertSee(\App\Support\VersionedAsset::url('js/tte-request.js'), false)
        ->assertDontSee('css/public-request-v5.css');

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(\App\Support\VersionedAsset::url('js/tte-app.js'), false)
        ->assertDontSee('js/tte-admin-period-v614.js');

    $this->get(route('admin.templates.index'))
        ->assertOk()
        ->assertSee(\App\Support\VersionedAsset::url('js/tte-app.js'), false)
        ->assertDontSee('js/tte-preset-preview.js');
});

it('uses the survey bundle on normal, thank-you, and rate-limited survey views', function () {
    foreach (
        ['public/survey', 'public/survey-thanks', 'public/survey-rate-limited', 'admin/surveys']
        as $view
    ) {
        $source = file_get_contents(resource_path('views/' . $view . '.blade.php'));
        expect($source)->toContain('tte-survey.css')->not->toContain('css/survey.css');
    }
});

it('preserves the distinct AMS, WhatsApp and responsive-interface logic', function () {
    $ticketJs = file_get_contents(public_path('js/tte-ticket.js'));
    $requestJs = file_get_contents(public_path('js/tte-request.js'));
    $appJs = file_get_contents(public_path('js/tte-app.js'));
    $ticketCss = file_get_contents(public_path('css/tte-ticket.css'));

    expect($ticketJs)
        ->toContain('ams-verification-form')
        ->toContain('visibilitychange')
        ->toContain('whatsapp://');
    expect($requestJs)
        ->toContain('tte-public-form')
        ->toContain('data-tte-ticket-copy')
        ->toContain('data-email-admin-wa');
    expect($appJs)
        ->toContain('tte-sidebar-collapsed-v1')
        ->toContain('tte-ux-period')
        ->toContain('tte-preset-detail');
    expect($ticketCss)->toContain('.ams-conditional[hidden]');
});
it('builds stable content-hash URLs for every published CSS and JavaScript bundle', function () {
    foreach (
        ['css/tte-app.css', 'css/tte-request.css', 'css/tte-ticket.css',
         'css/tte-survey.css', 'js/tte-app.js', 'js/tte-request.js', 'js/tte-ticket.js']
        as $path
    ) {
        $digest = substr(hash_file('sha256', public_path($path)), 0, 12);
        expect(\App\Support\VersionedAsset::version($path))->toBe($digest);
        expect(\App\Support\VersionedAsset::url($path))
            ->toBe(asset($path) . '?v=' . $digest);
    }
});

it('uses dynamic asset hashes on public and authenticated pages', function () {
    $this->get(route('public.form'))
        ->assertOk()
        ->assertSee(\App\Support\VersionedAsset::url('css/tte-app.css'), false)
        ->assertSee(\App\Support\VersionedAsset::url('css/tte-request.css'), false)
        ->assertSee(\App\Support\VersionedAsset::url('js/tte-request.js'), false);

    $admin = \App\Models\User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(\App\Support\VersionedAsset::url('js/tte-app.js'), false);
});

it('rejects unsafe or unavailable asset paths', function () {
    expect(fn () => \App\Support\VersionedAsset::url('../.env'))
        ->toThrow(\InvalidArgumentException::class);
    expect(fn () => \App\Support\VersionedAsset::url('css/nonexistent-test-file.css'))
        ->toThrow(\RuntimeException::class);
});
