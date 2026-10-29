<?php

namespace App\Services;

use App\Models\EmailDirectory;
use Illuminate\Support\Facades\DB;

class EmailDirectoryService
{
    public function nikHash(string $nik): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new \RuntimeException('APP_KEY harus dikonfigurasi.');
        }

        return hash_hmac('sha256', $nik, $key);
    }

    public function find(string $nik): ?EmailDirectory
    {
        return EmailDirectory::where('nik_hash', $this->nikHash($nik))->first();
    }

    public function available(): bool
    {
        $lastSynced = DB::table('email_directories')->max('synced_at');
        if (! $lastSynced) {
            return false;
        }
        $maxAge = max(1, (int) config('email_directory.max_age_hours', 168));

        return \Illuminate\Support\Carbon::parse($lastSynced)->greaterThanOrEqualTo(now()->subHours($maxAge));
    }

    public function normalizedEmail(?string $raw): ?string
    {
        $raw = strtolower(trim((string) $raw));
        if ($raw === '') {
            return null;
        }
        if (! str_contains($raw, '@')) {
            $raw .= '@subang.go.id';
        }

        return filter_var($raw, FILTER_VALIDATE_EMAIL)
            && preg_match('/^[a-z0-9._%+\-]+@subang\.go\.id$/D', $raw) ? $raw : null;
    }

    public function maskedEmail(string $email): string
    {
        [$username, $domain] = explode('@', $email, 2);

        return substr($username, 0, min(2, strlen($username))).'***@'.$domain;
    }
}
