<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('displays a hasil survei menu to admins and marks it active on the survey page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Hasil Survei')
        ->assertSee('href="'.route('admin.surveys.index').'"', false);

    $this->actingAs($admin)
        ->get(route('admin.surveys.index'))
        ->assertOk()
        ->assertSee('admin/survei')
        ->assertSee('Hasil Survei')
        ->assertSee('active', false);
});

it('hides survey admin navigation from verifiers and forbids survey reports', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);

    $this->actingAs($verifier)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertDontSee('Hasil Survei');

    $this->actingAs($verifier)
        ->get(route('admin.surveys.index'))
        ->assertForbidden();
});
