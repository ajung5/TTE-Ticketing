<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
uses(RefreshDatabase::class);

it('shows an editable own profile for verifier and preserves a read-only role', function () {
    $verifier = User::factory()->create(['role' => 'verifier', 'name' => 'Petugas Contoh']);
    $this->actingAs($verifier)
        ->get(route('profile'))
        ->assertOk()
        ->assertSee('Simpan Perubahan Profil')
        ->assertSee('name="initials"', false)
        ->assertSee('value="Verifikator"', false)
        ->assertSee('readonly', false);
});

it(
    'lets a verifier change own name and freely chosen initials without changing security attributes',
    function () {
        $verifier = User::factory()->create([
            'role' => 'verifier',
            'name' => 'Petugas Awal',
            'email' => 'verifier-self@example.test',
            'password' => 'SandiAwal!2026',
            'initials' => 'AW',
        ]);
        $oldHash = $verifier->password;
        $this->actingAs($verifier)
            ->patch(route('profile.update'), [
                'name' => 'Petugas Profil Terbaru',
                'email' => $verifier->email,
                'initials' => 'Aj. 01 / TTE',
                'role' => 'admin',
                'password' => 'PasswordPaksaan!2026',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasNoErrors();
        $verifier->refresh();
        expect($verifier->name)
            ->toBe('Petugas Profil Terbaru')
            ->and($verifier->initials)
            ->toBe('Aj. 01 / TTE')
            ->and($verifier->role)
            ->toBe('verifier')
            ->and($verifier->password)
            ->toBe($oldHash);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $verifier->id,
            'target_type' => 'user',
            'target_id' => $verifier->id,
            'action' => 'verifier_updated_own_profile',
        ]);
    },
);

it(
    'requires the current password to change a verifier email and only changes own account',
    function () {
        $verifier = User::factory()->create([
            'role' => 'verifier',
            'name' => 'Petugas Email',
            'email' => 'verifier-email-old@example.test',
            'password' => 'RahasiaAwal!2026',
        ]);
        $other = User::factory()->create([
            'role' => 'verifier',
            'name' => 'Petugas Lain',
            'email' => 'verifier-other@example.test',
        ]);
        $payload = [
            'name' => 'Petugas Email',
            'email' => 'verifier-email-new@example.test',
            'initials' => 'sandi.01',
            'user_id' => $other->id,
        ];
        $this->actingAs($verifier)
            ->patch(route('profile.update'), $payload)
            ->assertSessionHasErrors('current_password');
        expect($verifier->fresh()->email)->toBe('verifier-email-old@example.test');
        $this->patch(
            route('profile.update'),
            $payload + ['current_password' => 'SalahSandi'],
        )->assertSessionHasErrors('current_password');
        $this->patch(route('profile.update'), $payload + ['current_password' => 'RahasiaAwal!2026'])
            ->assertRedirect(route('profile'))
            ->assertSessionHasNoErrors();
        expect($verifier->fresh()->email)
            ->toBe('verifier-email-new@example.test')
            ->and($verifier->fresh()->initials)
            ->toBe('sandi.01')
            ->and($other->fresh()->email)
            ->toBe('verifier-other@example.test');
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $verifier->id,
            'target_id' => $verifier->id,
            'action' => 'verifier_updated_own_profile',
        ]);
    },
);

it('rejects duplicate email and unsafe initials without changing the profile', function () {
    $verifier = User::factory()->create([
        'role' => 'verifier',
        'name' => 'Petugas Validasi',
        'email' => 'verifier-validate@example.test',
    ]);
    $other = User::factory()->create([
        'role' => 'verifier',
        'email' => 'already-used@example.test',
    ]);
    $base = ['name' => 'Petugas Validasi', 'email' => $verifier->email];
    $this->actingAs($verifier)
        ->patch(route('profile.update'), [
            'name' => 'Petugas Validasi',
            'email' => $other->email,
            'initials' => 'OK',
        ])
        ->assertSessionHasErrors('email');
    foreach (["A\nB", '13-char-limit'] as $badInitials) {
        $this->patch(
            route('profile.update'),
            $base + ['initials' => $badInitials],
        )->assertSessionHasErrors('initials');
    }
    expect($verifier->fresh()->email)->toBe('verifier-validate@example.test');
});

it('keeps administrator profile editing intact and verifier admin routes forbidden', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'name' => 'Admin Lama',
        'email' => 'admin-profile@example.test',
    ]);
    $this->actingAs($admin)
        ->patch(route('profile.update'), [
            'name' => 'Admin Terbaru',
            'email' => $admin->email,
            'initials' => 'ADM',
        ])
        ->assertRedirect(route('profile'))
        ->assertSessionHasNoErrors();
    expect($admin->fresh()->name)->toBe('Admin Terbaru');
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'target_id' => $admin->id,
        'action' => 'admin_updated_own_profile',
    ]);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $this->actingAs($verifier)->get(route('admin.verifiers.index'))->assertForbidden();
    $this->get(route('admin.templates.index'))->assertForbidden();
});
