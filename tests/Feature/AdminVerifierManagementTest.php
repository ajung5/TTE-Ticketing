<?php

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function verifierAccountData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Petugas Verifikasi Contoh',
        'email' => 'petugas.verifikasi@example.test',
        'initials' => 'PVC',
        'password' => 'ContohPassword!2026',
        'password_confirmation' => 'ContohPassword!2026',
    ], $overrides);
}

it('only exposes verifier management to administrators', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->get(route('admin.verifiers.index'))->assertRedirect(route('login'));
    $this->actingAs($verifier)->get(route('admin.verifiers.index'))->assertForbidden();
    $this->actingAs($verifier)->post(route('admin.verifiers.store'), verifierAccountData())->assertForbidden();
    $this->actingAs($admin)->get(route('admin.verifiers.index'))
        ->assertOk()->assertSee('Manajemen Verifikator');
});

it('allows admins to add verifier accounts with strongly hashed passwords', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.verifiers.store'), verifierAccountData())
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
    $this->actingAs($admin)->post(route('admin.verifiers.store'), verifierAccountData([
        'password' => 'abc', 'password_confirmation' => 'abc',
    ]))->assertSessionHasErrors('password');

    $this->actingAs($admin)->post(route('admin.verifiers.store'), verifierAccountData())
        ->assertRedirect(route('admin.verifiers.index'));
    $this->actingAs($admin)->post(route('admin.verifiers.store'), verifierAccountData())
        ->assertSessionHasErrors('email');

    $this->actingAs($admin)->put(route('admin.verifiers.update', $admin), [
        'name' => 'Diubah', 'email' => 'diubah@example.test',
    ])->assertNotFound();
    $this->actingAs($admin)->delete(route('admin.verifiers.destroy', $admin))->assertNotFound();
});

it('allows admins to edit verifier profile without resetting the password', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create([
        'role' => 'verifier', 'password' => 'SandiAwal!2026',
    ]);

    $this->actingAs($admin)->put(route('admin.verifiers.update', $verifier), [
        'name' => 'Nama Profil Terbaru',
        'email' => 'profil.baru@example.test',
        'initials' => 'NBT',
        'password' => '', 'password_confirmation' => '',
    ])->assertRedirect(route('admin.verifiers.index'));

    expect($verifier->fresh()->name)->toBe('Nama Profil Terbaru');
    expect($verifier->fresh()->email)->toBe('profil.baru@example.test');
    expect(Hash::check('SandiAwal!2026', $verifier->fresh()->password))->toBeTrue();
});

it('allows admins to change verifier passwords without exposing existing credentials', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier', 'password' => 'SandiAwal!2026']);

    $this->actingAs($admin)->put(route('admin.verifiers.update', $verifier), [
        'name' => $verifier->name,
        'email' => $verifier->email,
        'initials' => 'PTG',
        'password' => 'PasswordPengganti!2026',
        'password_confirmation' => 'PasswordPengganti!2026',
    ])->assertRedirect(route('admin.verifiers.index'));

    expect(Hash::check('PasswordPengganti!2026', $verifier->fresh()->password))->toBeTrue();
    expect(Hash::check('SandiAwal!2026', $verifier->fresh()->password))->toBeFalse();
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin_changed_verifier_password', 'target_id' => $verifier->id,
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

    $this->actingAs($admin)->delete(route('admin.verifiers.destroy', $verifier))
        ->assertSessionHasErrors('verifier');
    expect(User::find($verifier->id))->not->toBeNull();
});

it('soft deletes unused verifiers while retaining their historical ticket and activity attribution', function () {
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

    $this->actingAs($admin)->delete(route('admin.verifiers.destroy', $verifier))
        ->assertRedirect(route('admin.verifiers.index'));

    expect(User::find($verifier->id))->toBeNull();
    expect(User::withTrashed()->findOrFail($verifier->id)->trashed())->toBeTrue();
    expect($ticket->fresh()->claimant?->id)->toBe($verifier->id);
    expect($activity->fresh()->actor?->id)->toBe($verifier->id);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin_deleted_verifier', 'target_id' => $verifier->id,
    ]);
});

it('reactivates a previous verifier when the administrator creates an account with the same email', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier', 'email' => 'petugas.verifikasi@example.test']);
    $verifier->delete();

    $this->actingAs($admin)->post(route('admin.verifiers.store'), verifierAccountData())
        ->assertRedirect(route('admin.verifiers.index'));

    expect(User::where('email', 'petugas.verifikasi@example.test')->firstOrFail()->id)->toBe($verifier->id);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin_reactivated_verifier', 'target_id' => $verifier->id,
    ]);
});
