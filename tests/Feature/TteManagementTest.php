<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function newTteTicket(array $attrs = []): Ticket {
    return Ticket::create(
        array_merge(
            [
                'nama_user' => 'Pemohon Uji',
                'no_wa' => '6281212345678',
                'layanan' => 'Reset Passphrase',
                'status' => 'Open',
                'source' => 'demo',
                'wa_verification_status' => 'verified'
            ],
            $attrs
        )
    );
}

it('requires login for the ticket queue', function () {
    $this->get('/tickets')->assertRedirect('/login');
});

it('prevents admin from accessing operational ticket endpoints', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = newTteTicket();
    $this->actingAs($admin)->get('/tickets')->assertForbidden();
    $this->actingAs($admin)->post(route('tickets.claim', $ticket))->assertForbidden();
});

it('only allows the first verifier to claim a ticket', function () {
    $one = User::factory()->create(['role' => 'verifier']);
    $two = User::factory()->create(['role' => 'verifier']);
    $ticket = newTteTicket();
    $this->actingAs($one)->post(route('tickets.claim', $ticket))->assertRedirect();
    $this->actingAs($two)->post(route('tickets.claim', $ticket))->assertStatus(409);
    expect($ticket->fresh()->claimed_by)->toBe($one->id);
});

it('prevents other verifiers from completing a claimed ticket', function () {
    $one = User::factory()->create(['role' => 'verifier']);
    $two = User::factory()->create(['role' => 'verifier']);
    $ticket = newTteTicket(['status' => 'In Progress', 'claimed_by' => $one->id]);
    $this->actingAs($two)
        ->post(route('tickets.complete', $ticket), ['resolution_note' => 'Sudah dikonfirmasi dan selesai'])
        ->assertForbidden();
});

it('requires a resolution note and records completion', function () {
    $user = User::factory()->create(['role' => 'verifier']);
    $ticket = newTteTicket(['status' => 'In Progress', 'claimed_by' => $user->id]);
    $this->actingAs($user)
        ->post(route('tickets.complete', $ticket), ['resolution_note' => 'Pendek'])
        ->assertSessionHasErrors();
    $this->actingAs($user)
        ->post(route('tickets.complete', $ticket), ['resolution_note' => 'Pekerjaan telah diselesaikan dan dicek.'])
        ->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Done');
    $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'completed']);
});

it('rejects unauthenticated Zoho webhook and accepts idempotent submissions', function () {
    config()->set('tte.zoho_webhook_secret', str_repeat('a', 32));
    $payload = [
        'submission_id' => 'zoho-001',
        'nama_user' => 'Penguji',
        'no_wa' => '081212345678',
        'layanan' => 'Pendaftaran'
    ];
    $this->postJson('/api/tickets/zoho', $payload)->assertUnauthorized();
    $headers = ['X-Zoho-Webhook-Secret' => str_repeat('a', 32)];
    $this->postJson('/api/tickets/zoho', $payload, $headers)->assertCreated();
    $this->postJson('/api/tickets/zoho', $payload, $headers)->assertOk()->assertJsonPath('duplicate', true);
    expect(Ticket::where('external_submission_id', 'zoho-001')->count())->toBe(1);
});

it('allows only admins to request verifier password reset', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $this->actingAs($verifier)->post(route('admin.password-reset', $verifier))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.password-reset', $verifier))->assertSessionHas('success');
    $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'target_id' => $verifier->id]);
});

it('limits admin to statistics and allows only admin to view aggregates', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = newTteTicket();
    $this->actingAs($verifier)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('tickets.show', $ticket))->assertForbidden();
});

it('only allows releasing a claim before recording any follow-up', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = newTteTicket();
    $this->actingAs($verifier)->post(route('tickets.claim', $ticket))->assertRedirect();
    $this->actingAs($verifier)
        ->post(route('tickets.note', $ticket), ['note' => 'Sudah dicek kelengkapannya'])
        ->assertRedirect();
    $this->actingAs($verifier)->post(route('tickets.release', $ticket))->assertStatus(409);
    expect($ticket->fresh()->claimed_by)->toBe($verifier->id);
});

it('does not mark a WhatsApp message sent until manually confirmed', function () {
    $verifier = User::factory()->create(['role' => 'verifier', 'initials' => 'VP']);
    $ticket = newTteTicket([
        'status' => 'Done',
        'claimed_by' => $verifier->id,
        'ticket_number' => 'TTE-20261028-000099',
        'completed_at' => now(),
        'resolution_note' => 'Proses selesai'
    ]);
    $this->actingAs($verifier)->post(route('tickets.whatsapp', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', ['ticket_id' => $ticket->id, 'confirmed_at' => null]);
    $this->actingAs($verifier)->post(route('tickets.whatsapp.confirm', $ticket))->assertRedirect();
    expect(\App\Models\WaNotification::where('ticket_id', $ticket->id)->first()->confirmed_at)->not->toBeNull();
});

it('allows a user to change their password with the correct current password', function () {
    $verifier = User::factory()->create(['role' => 'verifier', 'password' => 'PasswordLama!1234']);
    $this->actingAs($verifier)
        ->post(route('profile.password'), [
            'current_password' => 'salah',
            'password' => 'PasswordBaru!1234',
            'password_confirmation' => 'PasswordBaru!1234'
        ])
        ->assertSessionHasErrors();
    $this->actingAs($verifier)
        ->post(route('profile.password'), [
            'current_password' => 'PasswordLama!1234',
            'password' => 'PasswordBaru!1234',
            'password_confirmation' => 'PasswordBaru!1234'
        ])
        ->assertSessionHas('success');
    expect(\Illuminate\Support\Facades\Hash::check('PasswordBaru!1234', $verifier->fresh()->password))->toBeTrue();
});

it('loads the login page from the site root on a fresh install', function () {
    $this->get('/')->assertRedirect(route('public.form'));
    $this->get('/login')->assertOk();
});

it('does not show tickets assigned to another verifier in the queue', function () {
    $one = User::factory()->create(['role' => 'verifier']);
    $two = User::factory()->create(['role' => 'verifier']);
    $mine = newTteTicket([
        'nama_user' => 'Pemohon Untuk Verifikator Satu',
        'status' => 'In Progress',
        'claimed_by' => $one->id
    ]);
    $others = newTteTicket([
        'nama_user' => 'Pemohon Rahasia Milik Verifikator Dua',
        'status' => 'In Progress',
        'claimed_by' => $two->id
    ]);
    $this->actingAs($one)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertSee($mine->nama_user)
        ->assertDontSee($others->nama_user);
    $this->actingAs($one)->get(route('tickets.show', $others))->assertForbidden();
});

it('requires webhook content type JSON', function () {
    config()->set('tte.zoho_webhook_secret', str_repeat('c', 32));
    $this->call(
        'POST',
        '/api/tickets/zoho',
        [
            'submission_id' => 'zoho-002',
            'nama_user' => 'Penguji',
            'no_wa' => '081234567890',
            'layanan' => 'Pembaruan'
        ],
        server: ['HTTP_X_ZOHO_WEBHOOK_SECRET' => str_repeat('c', 32)]
    )->assertStatus(415);
});

it('offers a follow-up WhatsApp only after recording an actual follow-up', function () {
    $verifier = User::factory()->create(['role' => 'verifier', 'initials' => 'AC']);
    $ticket = newTteTicket([
        'status' => 'In Progress',
        'claimed_by' => $verifier->id,
        'ticket_number' => 'TTE-TEST-000003'
    ]);
    $this->actingAs($verifier)->post(route('tickets.whatsapp.followup', $ticket))->assertStatus(409);
    $this->actingAs($verifier)
        ->post(route('tickets.note', $ticket), ['note' => 'Identitas pemohon diverifikasi dengan SOP.'])
        ->assertRedirect();
    $this->actingAs($verifier)->post(route('tickets.whatsapp.followup', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', [
        'ticket_id' => $ticket->id,
        'event_type' => 'followup',
        'confirmed_at' => null
    ]);
    $this->actingAs($verifier)->post(route('tickets.whatsapp.followup.confirm', $ticket))->assertRedirect();
    $this->assertDatabaseMissing('wa_notifications', [
        'ticket_id' => $ticket->id,
        'event_type' => 'followup',
        'confirmed_at' => null
    ]);
});
