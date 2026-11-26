<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows only overdue open and owned active tickets and preserves verifier ownership', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $this->freezeTime();
    foreach ([
        ['OLD-OPEN', 'Open', null, 25, false],
        ['OLD-MINE', 'In Progress', $owner->id, 26, true],
        ['OLD-OTHER', 'In Progress', $other->id, 27, false],
        ['NEW-MINE', 'In Progress', $owner->id, 23, false],
        ['EXACT-24H', 'In Progress', $owner->id, 24, false],
        ['OLD-DONE', 'Done', $owner->id, 30, false],
        ['OLD-RETURNED', 'Returned', $owner->id, 30, false],
    ] as [$number, $status, $claimant, $hours, $waiting]) {
        $ticket = new Ticket([
            'nama_user' => 'Pemohon ' . $number,
            'no_wa' => '6281234567890',
            'layanan' => 'Pendaftaran',
            'ticket_number' => $number,
            'status' => $status,
            'claimed_by' => $claimant,
            'awaiting_applicant_action' => $waiting,
        ]);
        $ticket->created_at = now()->subHours($hours);
        $ticket->save();
    }

    $this->actingAs($owner)->get(route('tickets.index'))
        ->assertOk()->assertSee('2 tiket belum selesai lebih dari 24 jam');
    $this->get(route('tickets.index', ['focus' => 'overdue']))
        ->assertOk()->assertSee('OLD-OPEN')->assertSee('OLD-MINE')
        ->assertDontSee('OLD-OTHER')->assertDontSee('NEW-MINE')
        ->assertDontSee('EXACT-24H')->assertDontSee('OLD-DONE')->assertDontSee('OLD-RETURNED');
    $ownedTicket = Ticket::where('ticket_number', 'OLD-MINE')->firstOrFail();
    $this->get(route('tickets.show', ['ticket' => $ownedTicket, 'queue' => ['focus' => 'overdue', 'page' => 1]]))
        ->assertOk();

    Ticket::whereIn('ticket_number', ['OLD-OPEN', 'OLD-MINE'])->update(['status' => 'Done']);
    $this->get(route('tickets.index'))->assertOk()->assertDontSee('id="overdue-reminder"', false);
});
