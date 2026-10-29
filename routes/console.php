<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Enable hourly synchronization only after Google Sheets credentials have been configured.
if (config('email_directory.spreadsheet_id') && config('email_directory.service_account_json')) {
    Schedule::command('tte:sync-email-directory')->hourly()->withoutOverlapping();
}
