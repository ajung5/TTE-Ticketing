<?php

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketResetResend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function newResendTicket(User $owner, string $status = 'In Progress', array $extra = []): Ticket {
    return Ticket::create(
        array_merge(
            [
                'nama_user' => 'Pemohon Verifikasi',
                'ticket_number' => 'TTE-RESEND-' . uniqid(),
                'no_wa' => '6281212345678',
                'email' => 'pegawai@subang.go.id',
                'layanan' => 'Reset Passphrase',
                'status' => $status,
                'source' => 'direct',
                'claimed_by' => $owner->id,
                'activation_status' => 'activated',
                'wa_verification_status' => 'verified',
                'certificate_issuance_status' => 'issued',
                'certificate_validity_status' => 'active',
            ],
            $extra,
        ),
    );
}

it(
    'lets only an admin reassign active tickets with a preserved number and full audit',
    function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = User::factory()->create(['role' => 'verifier']);
        $b = User::factory()->create(['role' => 'verifier']);
        $ticket = newResendTicket($a);
        $number = $ticket->ticket_number;

        $this->actingAs($a)->get(route('admin.transfers.index'))->assertForbidden();
        $this->actingAs($a)
            ->post(route('admin.transfers.store', $ticket), [
                'verifier_id' => $b->id,
                'reason' => 'Petugas berhalangan',
            ])
            ->assertForbidden();
        $this->actingAs($admin)
            ->get(route('admin.transfers.index'))
            ->assertOk()
            ->assertSee($number)
            ->assertSee($a->name);
        $this->post(route('admin.transfers.store', $ticket), [
            'verifier_id' => $b->id,
            'reason' => 'Petugas berhalangan',
        ])->assertRedirect(route('admin.transfers.index'));
        expect($ticket->fresh()->claimed_by)
            ->toBe($b->id)
            ->and($ticket->fresh()->status)
            ->toBe('In Progress')
            ->and($ticket->fresh()->ticket_number)
            ->toBe($number)
            ->and($ticket->fresh()->wa_verification_status)
            ->toBe('verified');
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'actor_id' => $admin->id,
            'action' => 'admin_reassigned',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'action' => 'admin_reassigned_ticket',
        ]);
        $this->actingAs($a)->get(route('tickets.show', $ticket))->assertForbidden();
        $this->actingAs($b)->get(route('tickets.show', $ticket))->assertOk();
    },
);

it('rejects wrong target, same verifier, and non-active ticket transfer', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create(['role' => 'verifier']);
    $deleted = User::factory()->create(['role' => 'verifier']);
    $deleted->delete();
    $ticket = newResendTicket($owner);
    $this->actingAs($admin)
        ->post(route('admin.transfers.store', $ticket), [
            'verifier_id' => $deleted->id,
            'reason' => 'Rotasi pekerjaan',
        ])
        ->assertSessionHasErrors('verifier_id');
    $this->post(route('admin.transfers.store', $ticket), [
        'verifier_id' => $admin->id,
        'reason' => 'Rotasi pekerjaan',
    ])->assertSessionHasErrors('verifier_id');
    $this->post(route('admin.transfers.store', $ticket), [
        'verifier_id' => $owner->id,
        'reason' => 'Rotasi pekerjaan',
    ])->assertStatus(409);
    $ticket->update(['status' => 'Done']);
    $target = User::factory()->create(['role' => 'verifier']);
    $this->post(route('admin.transfers.store', $ticket), [
        'verifier_id' => $target->id,
        'reason' => 'Rotasi pekerjaan',
    ])->assertStatus(409);
    expect($ticket->fresh()->claimed_by)->toBe($owner->id);
});

it('logs each manual AMS resend without sending the BSrE email', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = newResendTicket($owner);
    $payload = ['reason' => 'not_received', 'ams_confirmed' => '1'];
    $this->actingAs($other)
        ->post(route('tickets.reset-resends.store', $ticket), $payload)
        ->assertForbidden();
    $this->actingAs($admin)
        ->post(route('tickets.reset-resends.store', $ticket), $payload)
        ->assertForbidden();
    $this->actingAs($owner)
        ->post(route('tickets.reset-resends.store', $ticket), [
            'reason' => 'not_received',
        ])
        ->assertSessionHasErrors('ams_confirmed');
    $this->post(route('tickets.reset-resends.store', $ticket), $payload)->assertRedirect(
        route('tickets.show', $ticket),
    );
    $attempt = TicketResetResend::firstOrFail();
    expect($attempt->wa_opened_at)
        ->toBeNull()
        ->and($attempt->wa_confirmed_at)
        ->toBeNull()
        ->and($attempt->message)
        ->toContain('AMS BSrE')
        ->not->toContain('http://reset-link');
    $this->post(route('tickets.reset-resends.store', $ticket), $payload)->assertSessionHasErrors(
        'reason',
    );
    $this->post(route('tickets.reset-resends.confirm', [$ticket, $attempt]))->assertStatus(409);
    $this->postJson(route('tickets.reset-resends.whatsapp', [$ticket, $attempt]))
        ->assertOk()
        ->assertJsonPath(
            'url',
            'https://web.whatsapp.com/send?phone=6281212345678&text=' .
                rawurlencode($attempt->message),
        );
    $this->post(route('tickets.reset-resends.confirm', [$ticket, $attempt]))->assertRedirect(
        route('tickets.show', $ticket),
    );
    expect($attempt->fresh()->wa_confirmed_at)->not->toBeNull();
    expect(
        TicketActivity::where('ticket_id', $ticket->id)
            ->where('action', 'reset_resend_wa_confirmed')
            ->count(),
    )->toBe(1);
});

it('limits manual resend records to five per rolling day', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = newResendTicket($owner);
    $payload = ['reason' => 'not_received', 'ams_confirmed' => 1];
    $this->actingAs($owner);
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('tickets.reset-resends.store', $ticket), $payload)->assertRedirect();
        $this->travel(3)->minutes();
    }
    $this->post(route('tickets.reset-resends.store', $ticket), $payload)->assertSessionHasErrors(
        'reason',
    );
    expect(TicketResetResend::where('ticket_id', $ticket->id)->count())->toBe(5);
});

it(
    'allows post-completion follow-up and prevents old owners from confirming after transfer',
    function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = User::factory()->create(['role' => 'verifier']);
        $second = User::factory()->create(['role' => 'verifier']);
        $done = newResendTicket($first, 'Done');
        $this->actingAs($first)
            ->post(route('tickets.reset-resends.store', $done), [
                'reason' => 'expired',
                'ams_confirmed' => 1,
            ])
            ->assertRedirect();
        expect($done->fresh()->status)->toBe('Done');
        $ongoing = newResendTicket($first);
        $this->post(route('tickets.reset-resends.store', $ongoing), [
            'reason' => 'not_received',
            'ams_confirmed' => 1,
        ])->assertRedirect();
        $attempt = TicketResetResend::where('ticket_id', $ongoing->id)->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.transfers.store', $ongoing), [
                'verifier_id' => $second->id,
                'reason' => 'Perubahan penanggung jawab',
            ])
            ->assertRedirect();
        $this->actingAs($first)
            ->post(route('tickets.reset-resends.confirm', [$ongoing, $attempt]))
            ->assertForbidden();
        $this->actingAs($second)
            ->post(route('tickets.reset-resends.confirm', [$ongoing, $attempt]))
            ->assertForbidden();
        $this->post(route('tickets.reset-resends.store', $ongoing), [
            'reason' => 'cannot_open',
            'ams_confirmed' => 1,
        ])->assertSessionHasErrors('reason');
    },
);
