<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function workspaceTicket(User $owner, array $attributes = []): Ticket {
    return Ticket::create(array_merge([
        'nama_user' => 'Pemohon Contoh', 'ticket_number' => 'UAT-' . uniqid(),
        'no_wa' => '6281234567890', 'email' => 'user@example.test',
        'layanan' => 'Pendaftaran', 'opd' => 'OPD A', 'status' => 'In Progress',
        'source' => 'direct', 'claimed_by' => $owner->id,
        'wa_checked_at' => now(), 'activation_status' => 'not_activated',
    ], $attributes));
}

it('keeps the selected queue when opening a ticket and returning after a redirect', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = workspaceTicket($owner, ['awaiting_applicant_action' => true]);
    $this->actingAs($owner)->get(route('tickets.index'))->assertViewHas('status', 'In Progress');
    $queue = ['focus' => 'waiting', 'q' => 'Contoh', 'layanan' => 'Pendaftaran', 'page' => 1];
    $this->get(route('tickets.index', $queue))->assertOk()
        ->assertSee(route('tickets.show', ['ticket' => $ticket, 'queue' => $queue]))
        ->assertSee('name="focus" value="waiting"', false);
    $this->get(route('tickets.show', ['ticket' => $ticket, 'queue' => $queue]))->assertOk();
    $this->get(route('tickets.show', $ticket))->assertSee(route('tickets.index', $queue));
});

it('filters report detail and CSV consistently while keeping the full period summary', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $ticket = workspaceTicket($owner);
    $excluded = [
        workspaceTicket($owner, ['opd' => 'OPD B']),
        workspaceTicket($other),
        workspaceTicket($owner, ['layanan' => 'Pembaruan']),
        workspaceTicket($owner, ['nama_user' => 'Nama Berbeda']),
    ];
    $filters = ['q' => 'Contoh', 'opd' => 'OPD A', 'verifier' => $owner->id, 'layanan' => 'Pendaftaran'];
    $page = $this->actingAs($admin)->get(route('admin.reports.tickets.index', $filters))->assertOk();
    expect($page->viewData('tickets')->pluck('id')->all())->toBe([$ticket->id])
        ->and((int) $page->viewData('summary')->total)->toBe(5);
    $csv = $this->get(route('admin.reports.tickets.csv', $filters))->assertOk()->streamedContent();
    expect($csv)->toContain($ticket->ticket_number);
    foreach ($excluded as $row) {
        expect($csv)->not->toContain($row->ticket_number);
    }
});

it('keeps the targeted transfer form and prevents a stale assignment from overwriting a handoff', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [$former, $current, $target] = User::factory()->count(3)->create(['role' => 'verifier']);
    $ticket = workspaceTicket($current);
    $url = route('admin.transfers.index', ['owner' => $current->id]);
    $this->actingAs($admin)->from($url)
        ->post(route('admin.transfers.store', $ticket), [
            'expected_owner' => $former->id, 'verifier_id' => $target->id, 'reason' => 'Pergantian petugas',
        ])->assertRedirect($url)->assertSessionHasErrors('verifier_id');
    $this->get($url)->assertOk()->assertSee('Pergantian petugas');
    expect($ticket->fresh()->claimed_by)->toBe($current->id);
});

it('displays only overdue tickets of the selected kind on the admin dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create(['role' => 'verifier']);
    $old = workspaceTicket($owner, ['status' => 'Open', 'claimed_by' => null]);
    Ticket::whereKey($old->id)->update(['created_at' => now()->subDays(5)]);
    workspaceTicket($owner, ['status' => 'Open', 'claimed_by' => null]);
    $page = $this->actingAs($admin)->get(route('admin.dashboard', ['attention' => 'waiting']))->assertOk();
    expect($page->viewData('attentionList')->pluck('id')->all())->toBe([$old->id]);
    $this->actingAs($owner)->get(route('admin.dashboard', ['attention' => 'waiting']))->assertForbidden();
});
