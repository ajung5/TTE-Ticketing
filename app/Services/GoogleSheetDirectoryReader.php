<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleSheetDirectoryReader
{
    /** Read-only Google Sheets v4 API using a service account; no new Composer dependency. */
    public function rows(): array
    {
        $path = (string) config('email_directory.service_account_json');
        $id = (string) config('email_directory.spreadsheet_id');
        $range = (string) config('email_directory.range');
        if (! preg_match('/^[\w\-]{20,}$/', $id) || $path === '' || ! is_readable($path)) {
            throw new \RuntimeException('TTE_EMAIL_SHEET_ID atau path JSON service account belum dikonfigurasi.');
        }
        $account = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($account) || ($account['type'] ?? '') !== 'service_account'
            || empty($account['client_email']) || empty($account['private_key'])) {
            throw new \RuntimeException('Service account JSON tidak lengkap.');
        }
        $now = time();
        $encode = static fn (array $data) => rtrim(strtr(base64_encode(json_encode($data, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $head = $encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = $encode([
            'iss' => $account['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now, 'exp' => $now + 3600,
        ]);
        if (! openssl_sign($head.'.'.$payload, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Gagal menandatangani JWT Google API.');
        }
        $jwt = $head.'.'.$payload.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        $response = Http::timeout(20)->asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ])->throw()->json();
        $token = $response['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Token Google API tidak tersedia.');
        }
        $response = Http::timeout(30)->withToken($token)
            ->get('https://sheets.googleapis.com/v4/spreadsheets/'.$id.'/values/'.rawurlencode($range), [
                'valueRenderOption' => 'FORMATTED_VALUE',
            ])->throw()->json();

        return $response['values'] ?? [];
    }
}
