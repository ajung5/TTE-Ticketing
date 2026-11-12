<?php

namespace App\Console\Commands;

use App\Services\EmailDirectorySync;
use App\Services\GoogleSheetDirectoryReader;
use Illuminate\Console\Command;

class SyncEmailDirectory extends Command {
    protected $signature = 'tte:sync-email-directory
        {--csv= : CSV lokal untuk uji/fallback}
        {--allow-large-removal : Izinkan penghapusan massal setelah pemeriksaan spreadsheet manual}';
    protected $description = 'Sinkronisasi inkremental direktori email Google Sheets atau CSV';

    public function handle(EmailDirectorySync $sync, GoogleSheetDirectoryReader $google): int {
        try {
            $allowRemoval = (bool) $this->option('allow-large-removal');
            $count = $this->option('csv')
                ? $sync->fromCsv((string) $this->option('csv'), $allowRemoval)
                : $sync->import($google->rows(), $allowRemoval);
            $stats = $sync->stats();
            $this->info("Sinkronisasi berhasil: {$count} baris direktori email.");
            $this->line(
                "Baru: {$stats['created']} | Berubah: {$stats['updated']} | " .
                    "Tetap: {$stats['unchanged']} | Dihapus: {$stats['deleted']}",
            );
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Sinkronisasi gagal: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
