<?php

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// Source: AdminVerifierManagementTest.php
function verifierAccountData(array $overrides = []): array {
    return array_merge(
        [
            'name' => 'Petugas Verifikasi Contoh',
            'email' => 'petugas.verifikasi@example.test',
            'initials' => 'PVC',
            'password' => 'ContohPassword!2026',
            'password_confirmation' => 'ContohPassword!2026',
        ],
        $overrides,
    );
}

it('only exposes verifier management to administrators', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->get(route('admin.verifiers.index'))->assertRedirect(route('login'));
    $this->actingAs($verifier)->get(route('admin.verifiers.index'))->assertForbidden();
    $this->actingAs($verifier)
        ->post(route('admin.verifiers.store'), verifierAccountData())
        ->assertForbidden();
    $this->actingAs($admin)
        ->get(route('admin.verifiers.index'))
        ->assertOk()
        ->assertSee('Manajemen Verifikator');
});

it('allows admins to add verifier accounts with strongly hashed passwords', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.verifiers.store'), verifierAccountData())
        ->assertRedirect(route('admin.verifiers.index'));

    $new = User::where('email', 'petugas.verifikasi@example.test')->firstOrFail();
    expect($new->role)->toBe('verifier');
    expect($new->initials)->toBe('PVC');
    expect(Hash::check('ContohPassword!2026', $new->password))->toBeTrue();
    expect($new->password)->not->toBe('ContohPassword!2026');
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'target_id' => $new->id,
        'action' => 'admin_created_verifier',
    ]);
});

it('rejects weak passwords, duplicate emails and attempts to modify an admin account', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->post(
            route('admin.verifiers.store'),
            verifierAccountData([
                'password' => 'abc',
                'password_confirmation' => 'abc',
            ]),
        )
        ->assertSessionHasErrors('password');

    $this->actingAs($admin)
        ->post(route('admin.verifiers.store'), verifierAccountData())
        ->assertRedirect(route('admin.verifiers.index'));
    $this->actingAs($admin)
        ->post(route('admin.verifiers.store'), verifierAccountData())
        ->assertSessionHasErrors('email');

    $this->actingAs($admin)
        ->put(route('admin.verifiers.update', $admin), [
            'name' => 'Diubah',
            'email' => 'diubah@example.test',
        ])
        ->assertNotFound();
    $this->actingAs($admin)->delete(route('admin.verifiers.destroy', $admin))->assertNotFound();
});

it('allows admins to edit verifier profile without resetting the password', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create([
        'role' => 'verifier',
        'password' => 'SandiAwal!2026',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.verifiers.update', $verifier), [
            'name' => 'Nama Profil Terbaru',
            'email' => 'profil.baru@example.test',
            'initials' => 'NBT',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect(route('admin.verifiers.index'));

    expect($verifier->fresh()->name)->toBe('Nama Profil Terbaru');
    expect($verifier->fresh()->email)->toBe('profil.baru@example.test');
    expect(Hash::check('SandiAwal!2026', $verifier->fresh()->password))->toBeTrue();
});

it('allows admins to change verifier passwords without exposing existing credentials', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier', 'password' => 'SandiAwal!2026']);

    $this->actingAs($admin)
        ->put(route('admin.verifiers.update', $verifier), [
            'name' => $verifier->name,
            'email' => $verifier->email,
            'initials' => 'PTG',
            'password' => 'PasswordPengganti!2026',
            'password_confirmation' => 'PasswordPengganti!2026',
        ])
        ->assertRedirect(route('admin.verifiers.index'));

    expect(Hash::check('PasswordPengganti!2026', $verifier->fresh()->password))->toBeTrue();
    expect(Hash::check('SandiAwal!2026', $verifier->fresh()->password))->toBeFalse();
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin_changed_verifier_password',
        'target_id' => $verifier->id,
    ]);
});

it('prevents removing verifiers while they own active tickets', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    Ticket::create([
        'nama_user' => 'Pemohon Uji',
        'no_wa' => '6281234567890',
        'layanan' => 'Reset Passphrase',
        'status' => 'In Progress',
        'claimed_by' => $verifier->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.verifiers.destroy', $verifier))
        ->assertSessionHasErrors('verifier');
    expect(User::find($verifier->id))->not->toBeNull();
});

it(
    'soft deletes unused verifiers while retaining their historical ticket and activity attribution',
    function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $verifier = User::factory()->create(['role' => 'verifier']);
        $ticket = Ticket::create([
            'nama_user' => 'Pemohon Uji',
            'no_wa' => '6281234567890',
            'layanan' => 'Pembaruan',
            'status' => 'Done',
            'claimed_by' => $verifier->id,
            'completed_at' => now(),
        ]);
        $activity = TicketActivity::create([
            'ticket_id' => $ticket->id,
            'actor_id' => $verifier->id,
            'action' => 'completed',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.verifiers.destroy', $verifier))
            ->assertRedirect(route('admin.verifiers.index'));

        expect(User::find($verifier->id))->toBeNull();
        expect(User::withTrashed()->findOrFail($verifier->id)->trashed())->toBeTrue();
        expect($ticket->fresh()->claimant?->id)->toBe($verifier->id);
        expect($activity->fresh()->actor?->id)->toBe($verifier->id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin_deleted_verifier',
            'target_id' => $verifier->id,
        ]);
    },
);

it(
    'reactivates a previous verifier when the administrator creates an account with the same email',
    function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $verifier = User::factory()->create([
            'role' => 'verifier',
            'email' => 'petugas.verifikasi@example.test',
        ]);
        $verifier->delete();

        $this->actingAs($admin)
            ->post(route('admin.verifiers.store'), verifierAccountData())
            ->assertRedirect(route('admin.verifiers.index'));

        expect(User::where('email', 'petugas.verifikasi@example.test')->firstOrFail()->id)->toBe(
            $verifier->id,
        );
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin_reactivated_verifier',
            'target_id' => $verifier->id,
        ]);
    },
);

// Source: VerifierSelfProfileV616Test.php
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
