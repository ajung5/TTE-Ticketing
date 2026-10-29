<?php

namespace App\Console\Commands;

use App\Services\EmailDirectorySync;
use App\Services\GoogleSheetDirectoryReader;
use Illuminate\Console\Command;

class SyncEmailDirectory extends Command
{
    protected $signature = 'tte:sync-email-directory {--csv= : CSV lokal (untuk uji / fallback)}';
    protected $description = 'Sinkronisasi direktori email dari Google Sheets atau file CSV';

    public function handle(EmailDirectorySync $sync, GoogleSheetDirectoryReader $google): int
    {
        try {
            $count = $this->option('csv')
                ? $sync->fromCsv((string) $this->option('csv'))
                : $sync->import($google->rows());
            $this->info("Sinkronisasi berhasil: {$count} baris direktori email.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Sinkronisasi gagal: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
