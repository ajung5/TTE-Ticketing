<?php

use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function uxV614TicketAt(string $name, string $localDateTime, array $attributes = []): Ticket {
    $ticket = Ticket::create(
        array_merge(
            [
                'nama_user' => $name,
                'no_wa' => '6281234567890',
                'layanan' => 'Pembaruan',
                'status' => 'Open',
                'source' => 'direct',
            ],
            $attributes,
        ),
    );
    $ticket
        ->forceFill([
            'created_at' => CarbonImmutable::parse($localDateTime, 'Asia/Jakarta')->utc(),
        ])
        ->save();
    return $ticket;
}

it('counts custom dates inclusively in WIB and separately counts completions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    uxV614TicketAt('Mulai WIB', '2026-10-31 00:00:00');
    uxV614TicketAt('Akhir WIB', '2026-10-31 23:59:59');
    uxV614TicketAt('Sebelum WIB', '2026-10-30 23:59:59');
    uxV614TicketAt('Setelah WIB', '2026-11-01 00:00:00');
    uxV614TicketAt('Selesai dalam Periode', '2026-10-25 10:00:00', [
        'status' => 'Done',
        'completed_at' => CarbonImmutable::parse('2026-10-31 15:00:00', 'Asia/Jakarta')->utc(),
    ]);

    $response = $this->actingAs($admin)->get(
        route('admin.dashboard', [
            'period' => 'custom',
            'from' => '2026-10-31',
            'to' => '2026-10-31',
        ]),
    );
    $response
        ->assertOk()
        ->assertSee('Tanggal Kustom')
        ->assertSee('31 Oktober 2026')
        ->assertSee('id="tte-ux-period-custom-fields"', false)
        ->assertViewHas('periodStats', function (array $stats): bool {
            return $stats['incoming'] === 2 && $stats['completed'] === 1;
        });
});

it('rejects missing, malformed, and reversed custom ranges', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->get(
            route('admin.dashboard', [
                'period' => 'custom',
                'to' => '2026-10-31',
            ]),
        )
        ->assertSessionHasErrors('from');
    $this->actingAs($admin)
        ->get(
            route('admin.dashboard', [
                'period' => 'custom',
                'from' => '2026-10-31',
                'to' => '2026-10-01',
            ]),
        )
        ->assertSessionHasErrors('to');
    $this->actingAs($admin)
        ->get(
            route('admin.dashboard', [
                'period' => 'custom',
                'from' => '2026-02-30',
                'to' => '2026-03-01',
            ]),
        )
        ->assertSessionHasErrors('from');
});

it('preserves existing presets and prevents non-admin access', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    foreach (['week', 'month', 'all'] as $period) {
        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['period' => $period]))
            ->assertOk()
            ->assertViewHas('period', $period);
    }
    $this->actingAs($verifier)
        ->get(
            route('admin.dashboard', [
                'period' => 'custom',
                'from' => '2026-10-01',
                'to' => '2026-10-31',
            ]),
        )
        ->assertForbidden();
});
