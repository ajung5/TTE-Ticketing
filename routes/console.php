<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler memeriksa Google Sheets tiap 5 menit, tetapi importer hanya
// mengubah record pegawai jika nilainya benar-benar berubah atau bertambah.
if (config('email_directory.spreadsheet_id') && config('email_directory.service_account_json')) {
    Schedule::command('tte:sync-email-directory')
        ->everyFiveMinutes()
        ->withoutOverlapping(15);
}
