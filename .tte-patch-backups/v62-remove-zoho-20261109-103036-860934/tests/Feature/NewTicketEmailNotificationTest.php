<?php

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewTicketCreatedNotification;
use App\Services\EmailDirectorySync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function singleInboxRows(): array
{
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

function singleInboxForm(?string $token = null): array
{
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

it('queues exactly one email for a new Zoho ticket and none for its duplicate', function () {
    Notification::fake();
    config()->set('tte.new_ticket_inbox_email', 'tiket-operasional@gmail.com');
    $secret = str_repeat('S', 32);
    config()->set('tte.zoho_webhook_secret', $secret);
    $payload = [
        'submission_id' => 'zoho-' . Str::uuid(),
        'nama_user' => 'Pemohon Uji',
        'no_wa' => '081234567890',
        'layanan' => 'Pembaruan',
        'opd' => 'Diskominfo',
    ];
    $headers = ['X-Zoho-Webhook-Secret' => $secret];
    $this->postJson('/api/tickets/zoho', $payload, $headers)->assertCreated();
    $this->postJson('/api/tickets/zoho', $payload, $headers)
        ->assertOk()
        ->assertJsonPath('duplicate', true);

    expect(Ticket::count())->toBe(1);
    Notification::assertCount(1);
    Notification::assertSentOnDemand(NewTicketCreatedNotification::class);
});

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
