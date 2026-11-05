<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

/** Read-only operational indicators. Configuration is NOT delivery/scheduler proof. */
class TteSystemHealth
{
    public function summary(): array
    {
        $count = DB::table('email_directories')->count();
        $last = DB::table('email_directories')->max('synced_at');
        $limit = max(1, (int) config('email_directory.max_age_hours', 168));
        $age = $last ? Carbon::parse($last)->diffInHours(now()) : null;
        $fresh = $count > 0 && $age !== null && Carbon::parse($last)->greaterThanOrEqualTo(now()->subHours($limit));
        $configuredSheet = filled(config('email_directory.spreadsheet_id'))
            && is_readable((string) config('email_directory.service_account_json'));
        $mailer = (string) config('mail.default', 'log');
        $inbox = (string) config('tte.new_ticket_inbox_email', '');
        $smtpReady = $mailer === 'smtp'
            && filled(config('mail.mailers.smtp.host'))
            && filled(config('mail.mailers.smtp.username'))
            && filled(config('mail.mailers.smtp.password'))
            && filter_var((string) config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            && filter_var($inbox, FILTER_VALIDATE_EMAIL);
        $queueDriver = (string) config('queue.default', 'sync');
        $pendingJobs = $queueDriver === 'database' && Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;

        return [
            'directory_count' => $count,
            'directory_last' => $last ? Carbon::parse($last)->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : 'Belum pernah',
            'directory_age_hours' => $age === null ? null : (int) floor($age),
            'directory_max_age' => $limit,
            'directory_fresh' => $fresh,
            'sheet_configured' => $configuredSheet,
            'mailer' => $mailer,
            'smtp_ready' => (bool) $smtpReady,
            'queue_driver' => $queueDriver,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
        ];
    }
}
