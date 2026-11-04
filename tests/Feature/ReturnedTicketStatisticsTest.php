<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function returnedStatisticsTicket(
    array $attributes = [],
    string $createdAt = '2026-10-15 03:00:00',
): Ticket {
    $ticket = Ticket::create(
        array_merge(
            [
                'ticket_number' => 'TTE-STAT-' . \Illuminate\Support\Str::random(12),
                'nama_user' => 'Pemohon Statistik',
                'no_wa' => '6281212345678',
                'layanan' => 'Pendaftaran',
                'status' => 'Open',
                'source' => 'direct',
            ],
            $attributes,
        ),
    );
    $ticket->forceFill(['created_at' => $createdAt])->save();
    return $ticket;
}

it('shows returned totals separately on the admin dashboard and per verifier', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $first = User::factory()->create(['role' => 'verifier']);
    $second = User::factory()->create(['role' => 'verifier']);
    returnedStatisticsTicket();
    returnedStatisticsTicket(['status' => 'In Progress', 'claimed_by' => $first->id]);
    returnedStatisticsTicket(['status' => 'Done', 'claimed_by' => $first->id]);
    returnedStatisticsTicket(['status' => 'Returned', 'claimed_by' => $first->id]);
    returnedStatisticsTicket(['status' => 'Returned', 'claimed_by' => $second->id]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));
    $response->assertOk()->assertSee('Perlu Pengajuan Ulang');
    expect($response->viewData('stats'))->toMatchArray([
        'total' => 5,
        'new' => 1,
        'active' => 1,
        'done' => 1,
        'returned' => 2,
    ]);
    $counts = $response->viewData('counts');
    expect((int) $counts[$first->id]->returned_count)
        ->toBe(1)
        ->and((int) $counts[$first->id]->done_count)
        ->toBe(1)
        ->and((int) $counts[$second->id]->returned_count)
        ->toBe(1)
        ->and((int) $counts[$second->id]->done_count)
        ->toBe(0);
});

it('limits verifier returned statistics and the returned queue to their own tickets', function () {
    $first = User::factory()->create(['role' => 'verifier']);
    $second = User::factory()->create(['role' => 'verifier']);
    $mine = returnedStatisticsTicket(['status' => 'Returned', 'claimed_by' => $first->id]);
    $other = returnedStatisticsTicket(['status' => 'Returned', 'claimed_by' => $second->id]);
    returnedStatisticsTicket(['status' => 'Returned', 'claimed_by' => $second->id]);
    returnedStatisticsTicket(['status' => 'Done', 'claimed_by' => $first->id]);

    $response = $this->actingAs($first)->get(route('tickets.index', ['status' => 'Returned']));
    $response
        ->assertOk()
        ->assertSee('Lihat tiket yang Anda kembalikan')
        ->assertSee($mine->ticket_number)
        ->assertDontSee($other->ticket_number);
    expect($response->viewData('stats')['returned'])
        ->toBe(1)
        ->and($response->viewData('stats')['completed'])
        ->toBe(1)
        ->and($response->viewData('tickets')->total())
        ->toBe(1);
});

it(
    'reconciles all incoming statuses in report totals service totals and verifier totals',
    function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $verifier = User::factory()->create(['role' => 'verifier', 'name' => 'Petugas Riwayat']);
        foreach (['Open', 'In Progress', 'Done', 'Returned'] as $status) {
            returnedStatisticsTicket([
                'status' => $status,
                'claimed_by' => $status === 'Open' ? null : $verifier->id,
            ]);
        }
        returnedStatisticsTicket([
            'status' => 'Returned',
            'claimed_by' => $verifier->id,
            'layanan' => 'Pembaruan',
        ]);
        returnedStatisticsTicket(['status' => 'Returned'], '2026-09-01 00:00:00');
        $verifier->delete();

        $response = $this->actingAs($admin)->get(
            route('admin.reports.tickets.index', [
                'mode' => 'month',
                'month' => '2026-10',
            ]),
        );
        $response
            ->assertOk()
            ->assertSee('Perlu Pengajuan Ulang')
            ->assertSee('Total Tiket Masuk')
            ->assertSee('Petugas Riwayat');
        $summary = $response->viewData('summary');
        expect((int) $summary->total)
            ->toBe(5)
            ->and(
                (int) $summary->open_count +
                    (int) $summary->progress_count +
                    (int) $summary->done_count +
                    (int) $summary->returned_count,
            )
            ->toBe(5)
            ->and((int) $summary->returned_count)
            ->toBe(2)
            ->and((int) $summary->done_count)
            ->toBe(1);
        $services = $response->viewData('byService')->keyBy('layanan');
        expect((int) $services['Pendaftaran']->returned_count)
            ->toBe(1)
            ->and((int) $services['Pembaruan']->returned_count)
            ->toBe(1);
        $stats = $response->viewData('verifierStats')->first();
        expect((int) $stats->total)->toBe(4)->and((int) $stats->returned_count)->toBe(2);
    },
);

it(
    'filters detail pages and full CSV by status while preserving the whole period summary',
    function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $returnedNumbers = [];
        for ($i = 0; $i < 25; $i++) {
            $returnedNumbers[] = returnedStatisticsTicket(['status' => 'Returned'])->ticket_number;
        }
        $done = returnedStatisticsTicket(['status' => 'Done']);
        $outside = returnedStatisticsTicket(['status' => 'Returned'], '2026-09-01 00:00:00');
        $filters = ['mode' => 'month', 'month' => '2026-10', 'status' => 'Returned'];

        $response = $this->actingAs($admin)->get(route('admin.reports.tickets.index', $filters));
        $response
            ->assertOk()
            ->assertDontSee($done->ticket_number)
            ->assertDontSee($outside->ticket_number)
            ->assertSee('25 tiket sesuai filter')
            ->assertSee(route('admin.reports.tickets.csv', $filters));
        expect((int) $response->viewData('summary')->total)
            ->toBe(26)
            ->and((int) $response->viewData('summary')->done_count)
            ->toBe(1)
            ->and($response->viewData('tickets')->total())
            ->toBe(25)
            ->and($response->viewData('tickets')->count())
            ->toBe(20);

        $page2 = $this->get(
            route('admin.reports.tickets.index', array_merge($filters, ['page' => 2])),
        );
        expect($page2->viewData('tickets')->count())
            ->toBe(5)
            ->and($page2->viewData('tickets')->pluck('status')->unique()->all())
            ->toBe(['Returned']);

        $csv = $this->get(route('admin.reports.tickets.csv', $filters));
        $csv->assertOk()->assertDownload('laporan-tiket-2026-10-returned.csv');
        $contents = $csv->streamedContent();
        expect($contents)
            ->toStartWith("\xEF\xBB\xBF")
            ->not->toContain($done->ticket_number)
            ->not->toContain($outside->ticket_number);
        foreach ($returnedNumbers as $number) {
            expect($contents)->toContain($number);
        }
        expect(substr_count($contents, 'Perlu Pengajuan Ulang'))->toBe(25);
    },
);

it('accepts every supported detail status and an empty status in both endpoints', function (
    string $status,
) {
    $admin = User::factory()->create(['role' => 'admin']);
    foreach (['Open', 'In Progress', 'Done', 'Returned'] as $value) {
        returnedStatisticsTicket(['status' => $value]);
    }
    $filters = ['mode' => 'month', 'month' => '2026-10', 'status' => $status];
    $response = $this->actingAs($admin)
        ->get(route('admin.reports.tickets.index', $filters))
        ->assertOk();
    expect($response->viewData('tickets')->total())
        ->toBe($status === '' ? 4 : 1)
        ->and((int) $response->viewData('summary')->total)
        ->toBe(4);
    $this->get(route('admin.reports.tickets.csv', $filters))->assertOk();
})->with(['Open', 'In Progress', 'Done', 'Returned', '']);

it('uses inclusive WIB date boundaries for returned detail and CSV filters', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $inside = returnedStatisticsTicket(['status' => 'Returned'], '2026-10-31 16:59:59');
    $start = returnedStatisticsTicket(['status' => 'Returned'], '2026-10-30 17:00:00');
    $before = returnedStatisticsTicket(['status' => 'Returned'], '2026-10-30 16:59:59');
    $after = returnedStatisticsTicket(['status' => 'Returned'], '2026-10-31 17:00:00');
    $filters = [
        'mode' => 'custom',
        'from' => '2026-10-31',
        'to' => '2026-10-31',
        'status' => 'Returned',
    ];
    $this->actingAs($admin)
        ->get(route('admin.reports.tickets.index', $filters))
        ->assertOk()
        ->assertSee($inside->ticket_number)
        ->assertSee($start->ticket_number)
        ->assertDontSee($before->ticket_number)
        ->assertDontSee($after->ticket_number);
    $csv = $this->get(route('admin.reports.tickets.csv', $filters))->streamedContent();
    expect($csv)
        ->toContain($inside->ticket_number)
        ->toContain($start->ticket_number)
        ->not->toContain($before->ticket_number)
        ->not->toContain($after->ticket_number);
});

it('does not count a returned ticket with a stale completion timestamp as completed', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    returnedStatisticsTicket(
        ['status' => 'Done', 'completed_at' => '2026-10-10 03:00:00'],
        '2026-09-01 00:00:00',
    );
    returnedStatisticsTicket(['status' => 'Returned', 'completed_at' => '2026-10-22 03:00:00']);
    $filters = ['mode' => 'month', 'month' => '2026-10', 'status' => 'Returned'];
    $response = $this->actingAs($admin)->get(route('admin.reports.tickets.index', $filters));
    $response->assertOk()->assertDontSee('22/10/2026 10:00');
    expect($response->viewData('completedDuringPeriod'))
        ->toBe(1)
        ->and((int) $response->viewData('summary')->done_count)
        ->toBe(0);
    $csv = $this->get(route('admin.reports.tickets.csv', $filters))->streamedContent();
    expect($csv)->not->toContain('22/10/2026 10:00');
});

it('rejects unsupported status filters in both report endpoints', function (string $route) {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->get(route($route, ['status' => 'Invalid']))
        ->assertSessionHasErrors('status');
})->with(['admin.reports.tickets.index', 'admin.reports.tickets.csv']);

it('shows an empty returned report without counting returned tickets as done', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $response = $this->actingAs($admin)
        ->get(
            route('admin.reports.tickets.index', [
                'mode' => 'month',
                'month' => '2026-10',
                'status' => 'Returned',
            ]),
        )
        ->assertOk()
        ->assertSee('Tidak ada tiket yang sesuai');
    expect((int) $response->viewData('summary')->total)
        ->toBe(0)
        ->and((int) $response->viewData('summary')->returned_count)
        ->toBe(0)
        ->and($response->viewData('completedDuringPeriod'))
        ->toBe(0);
});
