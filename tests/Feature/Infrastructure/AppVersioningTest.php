<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses the canonical VERSION file for the release number', function () {
    $version = trim((string) file_get_contents(base_path('VERSION')));
    expect($version)->toMatch('/\A(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\z/');
    expect(config('tte.version'))->toBe($version);
});

it('renders only the release version in the public footer', function () {
    $version = trim((string) file_get_contents(base_path('VERSION')));
    $this->get(route('public.form'))
        ->assertOk()
        ->assertSee('aria-label="Versi aplikasi"', false)
        ->assertSee('>v' . $version . '</span>', false);
});
