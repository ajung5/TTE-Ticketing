<?php

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewTicketCreatedNotification;
use App\Notifications\TtePasswordResetNotification;
use App\Services\EmailDirectorySync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// Source: NewTicketEmailNotificationTest.php
function singleInboxRows(): array {
    return [
        ['NIK', 'NIP', 'Email Gov', 'NAMA', 'JABATAN', 'UNIT KERJA'],
        [
            '3273010101010001',
            '198901012020011001',
            'pemohon@subang.go.id',
            'Pemohon Contoh',
            'Analis',
            'Diskominfo',
        ],
    ];
}

function singleInboxForm(?string $token = null): array {
    return [
        'layanan' => 'Pendaftaran',
        'nama_user' => 'Pemohon Contoh',
        'nik' => '3273010101010001',
        'nip' => '198901012020011001',
        'no_wa' => '85812341234',
        'opd' => 'Diskominfo',
        'jabatan' => 'Analis',
        'persetujuan' => '1',
        'submission_token' => $token ?? (string) Str::uuid(),
        'signature_data' =>
            'data:image/png;base64,' .
            base64_encode(file_get_contents(base_path('tests/Fixtures/signature-test.png'))),
    ];
}

it(
    'sends one mail-only notification to the configured inbox, never to verifier accounts',
    function () {
        Notification::fake();
        Storage::fake('local');
        config()->set('tte.new_ticket_inbox_email', 'tiket-operasional@gmail.com');
        app(EmailDirectorySync::class)->import(singleInboxRows());
        User::factory()->create(['role' => 'verifier', 'email' => 'first@example.test']);
        User::factory()->create(['role' => 'verifier', 'email' => 'second@gmail.com']);
        User::factory()->create(['role' => 'admin', 'email' => 'admin@example.test']);

        $this->post(route('public.submit'), singleInboxForm())->assertRedirect(
            route('public.success'),
        );

        $ticket = Ticket::firstOrFail();
        expect($ticket->status)->toBe('Open');
        Notification::assertCount(1);
        Notification::assertSentOnDemand(NewTicketCreatedNotification::class, function (
            NewTicketCreatedNotification $notice,
            array $channels,
            object $recipient,
        ) use ($ticket) {
            $mail = $notice->toMail($recipient);
            $body = implode(' ', $mail->introLines);
            expect($notice)->toBeInstanceOf(ShouldQueue::class);
            expect($channels)->toBe(['mail']);
            expect($recipient->routes['mail'])->toBe('tiket-operasional@gmail.com');
            expect($mail->subject)->toContain($ticket->ticket_number);
            expect($body)->toContain($ticket->ticket_number)->toContain('WIB');
            expect($body)->not->toContain('3273010101010001')->not->toContain('6285812341234');
            return true;
        });
    },
);

it('notifies the central inbox even when there are no verifier accounts yet', function () {
    Notification::fake();
    Storage::fake('local');
    config()->set('tte.new_ticket_inbox_email', 'tiket-operasional@gmail.com');
    app(EmailDirectorySync::class)->import(singleInboxRows());

    $this->post(route('public.submit'), singleInboxForm())->assertRedirect(route('public.success'));
    expect(Ticket::count())->toBe(1);
    Notification::assertCount(1);
    Notification::assertSentOnDemand(NewTicketCreatedNotification::class);
});

it(
    'does not send an additional email for repeated public submissions with the same token',
    function () {
        Notification::fake();
        Storage::fake('local');
        config()->set('tte.new_ticket_inbox_email', 'tiket-operasional@gmail.com');
        app(EmailDirectorySync::class)->import(singleInboxRows());
        $data = singleInboxForm();

        $this->post(route('public.submit'), $data)->assertRedirect(route('public.success'));
        $this->post(route('public.submit'), $data)->assertRedirect(route('public.success'));

        expect(Ticket::count())->toBe(1);
        Notification::assertCount(1);
    },
);

it('does not send mail if the central inbox is absent or invalid', function () {
    Notification::fake();
    Storage::fake('local');
    config()->set('tte.new_ticket_inbox_email', 'not-an-email');
    app(EmailDirectorySync::class)->import(singleInboxRows());

    $this->post(route('public.submit'), singleInboxForm())->assertRedirect(route('public.success'));
    expect(Ticket::count())->toBe(1);
    Notification::assertNothingSent();
});

it('does not lose a newly created ticket when queue dispatch fails', function () {
    Storage::fake('local');
    config()->set('tte.new_ticket_inbox_email', 'tiket-operasional@gmail.com');
    app(EmailDirectorySync::class)->import(singleInboxRows());
    // Simulate a real queue outage rather than mocking Notification::route().
    // A missing queue connection makes Laravel's queued notification dispatcher fail.
    // The notifier must catch that failure without rolling back a committed ticket.
    config()->set('queue.default', 'tte-queue-unavailable-for-test');
    Log::spy();

    $this->post(route('public.submit'), singleInboxForm())->assertRedirect(route('public.success'));
    $ticket = Ticket::firstOrFail();
    expect(Ticket::count())->toBe(1);
    expect($ticket->status)->toBe('Open');

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function ($message, $context) use ($ticket) {
            return $message === 'Gagal mengantrikan email ke mailbox tiket.' &&
                is_array($context) &&
                ($context['ticket_id'] ?? null) === $ticket->getKey() &&
                is_string($context['exception_class'] ?? null);
        });
});

// Source: TteEmailAndPasswordResetTest.php
it('shows forgot-password to guests and preserves role-specific login', function () {
    $this->get(route('login'))->assertOk()->assertSee('Lupa Password?');
    $this->get(route('password.request'))->assertOk()->assertSee('Kirim Tautan Reset Password');
    expect(route('password.request'))->toContain('/lupa-password');
});

it('sends a localized reset link to the requesting administrator only', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin-reset@example.test']);
    $verifier = User::factory()->create([
        'role' => 'verifier',
        'email' => 'verifier-reset@example.test',
    ]);
    $this->post(route('password.email'), ['email' => $admin->email])
        ->assertRedirect()
        ->assertSessionHas('success');
    Notification::assertSentTo($admin, TtePasswordResetNotification::class, function (
        $notification,
    ) use ($admin) {
        $mail = $notification->toMail($admin);
        expect($mail->subject)->toContain('TTE Ticketing');
        expect($mail->actionUrl)
            ->toStartWith(rtrim((string) config('app.url'), '/'))
            ->toContain('/reset-password/')
            ->toContain(urlencode($admin->email));
        return true;
    });
    Notification::assertNotSentTo($verifier, TtePasswordResetNotification::class);
});

it(
    'allows verifiers to reset a forgotten password using the emailed single-use token',
    function () {
        Notification::fake();
        $user = User::factory()->create([
            'role' => 'verifier',
            'email' => 'verifier-forgot@example.test',
        ]);
        $oldPassword = $user->password;
        $token = null;
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('success');
        Notification::assertSentTo($user, TtePasswordResetNotification::class, function (
            $notice,
        ) use (&$token) {
            $token = $notice->token;
            return is_string($token) && strlen($token) > 10;
        });
        expect($token)->not->toBeNull();
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Buat Password Baru');
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
    },
);

it(
    'does not reveal if an email exists and never sends to unknown or soft-deleted accounts',
    function () {
        Notification::fake();
        $user = User::factory()->create(['role' => 'verifier', 'email' => 'disabled@example.test']);
        $user->delete();
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->post(route('password.email'), ['email' => 'not-registered@example.test'])
            ->assertRedirect()
            ->assertSessionHas('success');
        Notification::assertNothingSent();
    },
);

it('admin action sends reset only to the selected verifier, not to admin or others', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $this->actingAs($admin)
        ->post(route('admin.password-reset', $target))
        ->assertRedirect()
        ->assertSessionHas('success');
    Notification::assertSentTo($target, TtePasswordResetNotification::class);
    Notification::assertNotSentTo($other, TtePasswordResetNotification::class);
    Notification::assertNotSentTo($admin, TtePasswordResetNotification::class);
});

it('prevents requesting forgot-password as an authenticated account', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('password.request'))->assertRedirect();
});
