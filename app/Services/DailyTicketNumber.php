<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class DailyTicketNumber {
    /** Reserve within the same transaction that creates the ticket. */
    public function reserve(?CarbonInterface $instant = null): string {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Nomor antrean harus dipesan dalam transaksi tiket.');
        }

        $local = ($instant ?? now())->copy()->timezone('Asia/Jakarta');
        $date = $local->toDateString();
        $prefix = 'TTE-' . $local->format('Ymd') . '-';

        // Unique date key serializes concurrent first submissions for this date.
        // SQLite obtains its write lock here; MySQL/PostgreSQL also lock the counter row.
        DB::table('ticket_daily_sequences')->insertOrIgnore([
            'queue_date' => $date,
            'last_number' => 0,
        ]);
        $counter = DB::table('ticket_daily_sequences')
            ->where('queue_date', $date)
            ->lockForUpdate()
            ->first();
        if (!$counter) {
            throw new RuntimeException('Gagal memperoleh pengunci nomor antrean.');
        }

        $last = (int) $counter->last_number;
        if ($last === 0) {
            // UTC storage is the existing application convention. Initialize only once.
            // Count already-existing tickets for this local date, irrespective of status.
            // A locking read avoids an older MySQL repeatable-read snapshot.
            $start = $local->copy()->startOfDay()->utc();
            $end = $local->copy()->startOfDay()->addDay()->utc();
            $last = DB::table('tickets')
                ->where('created_at', '>=', $start)
                ->where('created_at', '<', $end)
                ->lockForUpdate()
                ->pluck('id')
                ->count();
        }

        // Old tickets retain global-ID suffixes. Never overwrite an occupied number.
        do {
            $last++;
            if ($last > 999999) {
                throw new RuntimeException('Kapasitas nomor antrean harian telah habis.');
            }
            $number = $prefix . str_pad((string) $last, 6, '0', STR_PAD_LEFT);
            $occupied = DB::table('tickets')
                ->where('ticket_number', $number)
                ->lockForUpdate()
                ->first(['id']);
        } while ($occupied);

        DB::table('ticket_daily_sequences')
            ->where('queue_date', $date)
            ->update(['last_number' => $last]);

        return $number;
    }
}
