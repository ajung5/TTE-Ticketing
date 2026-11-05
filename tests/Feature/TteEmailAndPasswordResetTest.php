<?php

use App\Models\User;
use App\Notifications\NewTicketCreatedNotification;
use App\Notifications\TtePasswordResetNotification;
use App\Services\EmailDirectorySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('shows forgot-password to guests and preserves role-specific login', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Lupa Password?');
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Kirim Tautan Reset Password');
    expect(route('password.request'))->toContain('/lupa-password');
});

it('sends a localized reset link to the requesting administrator only', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin-reset@example.test']);
    $verifier = User::factory()->create(['role' => 'verifier', 'email' => 'verifier-reset@example.test']);
    $this->post(route('password.email'), ['email' => $admin->email])
        ->assertRedirect()
        ->assertSessionHas('success');
    Notification::assertSentTo($admin, TtePasswordResetNotification::class, function ($notification) use ($admin) {
        $mail = $notification->toMail($admin);
        expect($mail->subject)->toContain('TTE Ticketing');
        expect($mail->actionUrl)->toStartWith(rtrim((string) config('app.url'), '/'))
            ->toContain('/reset-password/')
            ->toContain(urlencode($admin->email));
        return true;
    });
    Notification::assertNotSentTo($verifier, TtePasswordResetNotification::class);
});

it('allows verifiers to reset a forgotten password using the emailed single-use token', function () {
    Notification::fake();
    $user = User::factory()->create(['role' => 'verifier', 'email' => 'verifier-forgot@example.test']);
    $oldPassword = $user->password;
    $token = null;
    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('success');
    Notification::assertSentTo($user, TtePasswordResetNotification::class, function ($notice) use (&$token) {
        $token = $notice->token;
        return is_string($token) && strlen($token) > 10;
    });
    expect($token)->not->toBeNull();
    $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->assertOk()->assertSee('Buat Password Baru');
    $data = [
        'token' => $token,
        'email' => $user->email,
        'password' => 'PasswordBaru!2026',
        'password_confirmation' => 'PasswordBaru!2026',
    ];
    $this->post(route('password.update'), $data)->assertRedirect(route('login'));
    expect($user->fresh()->password)->not->toBe($oldPassword);
    expect(Hash::check('PasswordBaru!2026', $user->fresh()->password))->toBeTrue();
    $this->post(route('password.update'), $data)->assertSessionHasErrors('email');
});

it('does not reveal if an email exists and never sends to unknown or soft-deleted accounts', function () {
    Notification::fake();
    $user = User::factory()->create(['role' => 'verifier', 'email' => 'disabled@example.test']);
    $user->delete();
    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect()->assertSessionHas('success');
    $this->post(route('password.email'), ['email' => 'not-registered@example.test'])
        ->assertRedirect()->assertSessionHas('success');
    Notification::assertNothingSent();
});

it('admin action sends reset only to the selected verifier, not to admin or others', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $this->actingAs($admin)
        ->post(route('admin.password-reset', $target))
        ->assertRedirect()->assertSessionHas('success');
    Notification::assertSentTo($target, TtePasswordResetNotification::class);
    Notification::assertNotSentTo($other, TtePasswordResetNotification::class);
    Notification::assertNotSentTo($admin, TtePasswordResetNotification::class);
});

it('prevents requesting forgot-password as an authenticated account', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('password.request'))->assertRedirect();
});
