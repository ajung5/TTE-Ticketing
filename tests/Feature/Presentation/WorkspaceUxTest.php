<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function workspaceTicket(User $owner, array $attributes = []): Ticket {
    return Ticket::create(
        array_merge(
            [
                'nama_user' => 'Pemohon Contoh',
                'ticket_number' => 'UAT-' . uniqid(),
                'no_wa' => '6281234567890',
                'email' => 'user@example.test',
                'layanan' => 'Pendaftaran',
                'opd' => 'OPD A',
                'status' => 'In Progress',
                'source' => 'direct',
                'claimed_by' => $owner->id,
                'wa_checked_at' => now(),
                'activation_status' => 'not_activated',
            ],
            $attributes,
        ),
    );
}

it('keeps the selected queue when opening a ticket and returning after a redirect', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = workspaceTicket($owner, ['awaiting_applicant_action' => true]);
    $this->actingAs($owner)->get(route('tickets.index'))->assertViewHas('status', 'In Progress');
    $queue = ['focus' => 'waiting', 'q' => 'Contoh', 'layanan' => 'Pendaftaran', 'page' => 1];
    $this->get(route('tickets.index', $queue))
        ->assertOk()
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
    $filters = [
        'q' => 'Contoh',
        'opd' => 'OPD A',
        'verifier' => $owner->id,
        'layanan' => 'Pendaftaran',
    ];
    $page = $this->actingAs($admin)
        ->get(route('admin.reports.tickets.index', $filters))
        ->assertOk();
    expect($page->viewData('tickets')->pluck('id')->all())
        ->toBe([$ticket->id])
        ->and((int) $page->viewData('summary')->total)
        ->toBe(5);
    $csv = $this->get(route('admin.reports.tickets.csv', $filters))->assertOk()->streamedContent();
    expect($csv)->toContain($ticket->ticket_number);
    foreach ($excluded as $row) {
        expect($csv)->not->toContain($row->ticket_number);
    }
});

it(
    'keeps the targeted transfer form and prevents a stale assignment from overwriting a handoff',
    function () {
        $admin = User::factory()->create(['role' => 'admin']);
        [$former, $current, $target] = User::factory()
            ->count(3)
            ->create(['role' => 'verifier']);
        $ticket = workspaceTicket($current);
        $url = route('admin.transfers.index', ['owner' => $current->id]);
        $this->actingAs($admin)
            ->from($url)
            ->post(route('admin.transfers.store', $ticket), [
                'expected_owner' => $former->id,
                'verifier_id' => $target->id,
                'reason' => 'Pergantian petugas',
            ])
            ->assertRedirect($url)
            ->assertSessionHasErrors('verifier_id');
        $this->get($url)->assertOk()->assertSee('Pergantian petugas');
        expect($ticket->fresh()->claimed_by)->toBe($current->id);
    },
);

it('displays only overdue tickets of the selected kind on the admin dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create(['role' => 'verifier']);
    $old = workspaceTicket($owner, ['status' => 'Open', 'claimed_by' => null]);
    Ticket::whereKey($old->id)->update(['created_at' => now()->subDays(5)]);
    workspaceTicket($owner, ['status' => 'Open', 'claimed_by' => null]);
    $page = $this->actingAs($admin)
        ->get(route('admin.dashboard', ['attention' => 'waiting']))
        ->assertOk();
    expect($page->viewData('attentionList')->pluck('id')->all())->toBe([$old->id]);
    $this->actingAs($owner)
        ->get(route('admin.dashboard', ['attention' => 'waiting']))
        ->assertForbidden();
});

it('opens only the requested row in the filtered transfer table', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [$owner, $target] = User::factory()
        ->count(2)
        ->create(['role' => 'verifier']);
    $first = workspaceTicket($owner);
    $second = workspaceTicket($owner);
    $outside = workspaceTicket($target);
    $filters = ['q' => 'Pemohon', 'owner' => $owner->id];
    $list = $this->actingAs($admin)
        ->get(route('admin.transfers.index', $filters))
        ->assertOk()
        ->assertSee('data-copy-target="nomor-tiket-' . $first->id . '"', false)
        ->assertSee('data-copy-target="nomor-tiket-' . $second->id . '"', false)
        ->assertDontSee('name="expected_owner"', false)
        ->assertDontSee($outside->ticket_number);
    $document = new DOMDocument();
    $document->loadHTML($list->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
    $xpath = new DOMXPath($document);
    expect(
        $xpath->evaluate(
            'count(//table[thead/tr/th[@scope="col" and normalize-space()="Aksi"]]/tbody/tr)',
        ),
    )->toBe(2.0);
    $edit = route('admin.transfers.index', $filters + ['alih' => $second->id]);
    $list->assertSee($edit . '#form-pengalihan');
    $this->get($edit)
        ->assertOk()
        ->assertSee(route('admin.transfers.store', ['ticket' => $second] + $filters))
        ->assertDontSee(route('admin.transfers.store', ['ticket' => $first] + $filters))
        ->assertSee(route('admin.transfers.index', $filters) . '#tiket-' . $second->id);
    $this->get(route('admin.transfers.index', $filters + ['alih' => $outside->id]))
        ->assertOk()
        ->assertDontSee('name="expected_owner"', false)
        ->assertDontSee($outside->ticket_number);
});

it('keeps transfer filters and success feedback when the final row leaves its page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [$owner, $target] = User::factory()
        ->count(2)
        ->create(['role' => 'verifier']);
    for ($i = 0; $i < 16; $i++) {
        workspaceTicket($owner);
    }
    $filters = ['q' => 'Pemohon', 'owner' => $owner->id, 'page' => 2];
    $url = route('admin.transfers.index', $filters);
    $page = $this->actingAs($admin)->get($url)->assertOk();
    $ticket = $page->viewData('tickets')->sole();
    $this->post(route('admin.transfers.store', ['ticket' => $ticket] + $filters), [
        'expected_owner' => $owner->id,
        'verifier_id' => $target->id,
        'reason' => 'Pergantian jadwal petugas',
    ])->assertRedirect($url);
    $redirect = $this->get($url)->assertRedirect();
    $location = $redirect->headers->get('Location');
    parse_str(parse_url($location, PHP_URL_QUERY), $query);
    expect((int) $query['page'])
        ->toBe(1)
        ->and((int) $query['owner'])
        ->toBe($owner->id)
        ->and($query['q'])
        ->toBe('Pemohon');
    $this->get($location)
        ->assertOk()
        ->assertSee('Tiket berhasil dialihkan')
        ->assertDontSee($ticket->ticket_number);
});

it(
    'shows an empty table or unavailable destination without offering an invalid transfer',
    function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'verifier']);
        $removed = User::factory()->create(['role' => 'verifier']);
        $removed->delete();
        $ticket = workspaceTicket($owner);
        $this->actingAs($admin)
            ->get(route('admin.transfers.index'))
            ->assertOk()
            ->assertSee('Belum ada verifikator pengganti')
            ->assertDontSee('aria-label="Alihkan tiket', false);
        $this->get(route('admin.transfers.index', ['alih' => $ticket->id]))
            ->assertOk()
            ->assertSee('Belum ada verifikator lain yang aktif')
            ->assertDontSee('Simpan pengalihan');
        $this->get(route('admin.transfers.index', ['q' => 'nomor-tidak-ditemukan']))
            ->assertOk()
            ->assertSee('colspan="6"', false)
            ->assertSee('Tidak ada tiket aktif yang sesuai pencarian.');
    },
);
