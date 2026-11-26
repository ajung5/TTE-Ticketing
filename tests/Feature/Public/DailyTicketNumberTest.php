<?php

use App\Models\Ticket;
use App\Services\DailyTicketNumber;
use App\Services\EmailDirectorySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function dailyQueueTicket(string $number, Carbon $instant): Ticket {
    $ticket = new Ticket([
        'nama_user' => 'Pemohon antrean',
        'no_wa' => '6281234567890',
        'layanan' => 'Pendaftaran',
        'status' => 'Open',
        'ticket_number' => $number,
    ]);
    $ticket->created_at = $instant;
    $ticket->updated_at = $instant;
    $ticket->save();
    return $ticket;
}

it('continues from five legacy tickets without renumbering and resets at WIB midnight', function () {
    $instant = Carbon::parse('2026-11-26 16:59:00', 'UTC');
    foreach (range(101, 105) as $id) {
        dailyQueueTicket(sprintf('TTE-20261126-%06d', $id), $instant);
    }
    $next = DB::transaction(function () use ($instant) {
        $number = app(DailyTicketNumber::class)->reserve($instant);
        dailyQueueTicket($number, $instant);
        return $number;
    });
    expect($next)->toBe('TTE-20261126-000006');
    expect(DB::transaction(fn() => app(DailyTicketNumber::class)->reserve($instant)))
        ->toBe('TTE-20261126-000007');
    expect(DB::transaction(fn() => app(DailyTicketNumber::class)->reserve($instant->copy()->addMinute())))
        ->toBe('TTE-20261127-000001');
    expect(Ticket::where('ticket_number', 'TTE-20261126-000101')->exists())->toBeTrue();
});

it('does not reuse a number occupied by a legacy ticket', function () {
    $instant = Carbon::parse('2026-11-26 03:00:00', 'UTC');
    dailyQueueTicket('TTE-20261126-000002', $instant);
    expect(DB::transaction(fn() => app(DailyTicketNumber::class)->reserve($instant)))
        ->toBe('TTE-20261126-000003');
});

it('rolls back the daily sequence if ticket creation fails', function () {
    $instant = Carbon::parse('2026-11-26 03:00:00', 'UTC');
    try {
        DB::transaction(function () use ($instant) {
            expect(app(DailyTicketNumber::class)->reserve($instant))->toBe('TTE-20261126-000001');
            throw new RuntimeException('rollback test');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('rollback test');
    }
    expect(DB::transaction(fn() => app(DailyTicketNumber::class)->reserve($instant)))
        ->toBe('TTE-20261126-000001');
});

it('uses the daily sequence on the public submit route without consuming a duplicate attempt', function () {
    $instant = Carbon::parse('2026-11-26 04:00:00', 'UTC');
    $this->travelTo($instant);
    Storage::fake('local');
    Mail::fake();
    app(EmailDirectorySync::class)->import([
        ['NIK', 'NIP', 'Email Gov', 'NAMA', 'JABATAN', 'UNIT KERJA'],
        ['3273010101010001', '198901012020011001', 'pemohon@subang.go.id', 'Pemohon Antrean', 'Analis', 'Diskominfo'],
    ]);
    foreach (range(101, 105) as $id) {
        dailyQueueTicket(sprintf('TTE-20261126-%06d', $id), $instant);
    }
    $data = [
        'layanan' => 'Pendaftaran',
        'nama_user' => 'Pemohon Antrean',
        'nik' => '3273010101010001',
        'nip' => '198901012020011001',
        'no_wa' => '85812341234',
        'opd' => 'Diskominfo',
        'jabatan' => 'Analis',
        'persetujuan' => '1',
        'submission_token' => (string) Str::uuid(),
        'signature_data' => 'data:image/png;base64,' . base64_encode(file_get_contents(base_path('tests/Fixtures/signature-test.png'))),
    ];
    $this->post(route('public.submit'), $data)
        ->assertRedirect(route('public.success'))
        ->assertSessionHas('submission_success_ticket', 'TTE-20261126-000006');
    $this->post(route('public.submit'), $data)
        ->assertRedirect(route('public.success'))
        ->assertSessionHas('submission_success_ticket', 'TTE-20261126-000006');
    $data['submission_token'] = (string) Str::uuid();
    $this->post(route('public.submit'), $data)
        ->assertRedirect(route('public.success'))
        ->assertSessionHas('active_request_notice', true);
    expect(Ticket::count())->toBe(6);
    expect((int) DB::table('ticket_daily_sequences')->where('queue_date', '2026-11-26')->value('last_number'))->toBe(6);
});
