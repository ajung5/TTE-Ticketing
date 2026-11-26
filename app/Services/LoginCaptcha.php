<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LoginCaptcha {
    public function configured(): bool {
        return filled(config('tte.turnstile.site_key'))
            && filled(config('tte.turnstile.secret_key'))
            && filled(config('tte.turnstile.hostname'));
    }

    public function verify(string $token): bool {
        if (!$this->configured() || $token === '' || strlen($token) > 2048) {
            return false;
        }

        try {
            // Do not forward credentials, NIK, email or the raw client IP.
            // Siteverify tokens are single-use and expire after five minutes.
            $response = Http::asForm()
                ->connectTimeout(3)
                ->timeout(8)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('tte.turnstile.secret_key'),
                    'response' => $token,
                ]);
            if (!$response->successful()) {
                return false;
            }
            $result = $response->json();

            return is_array($result)
                && ($result['success'] ?? false) === true
                && ($result['action'] ?? null) === 'tte_login'
                && is_string($result['hostname'] ?? null)
                && strtolower($result['hostname']) === strtolower((string) config('tte.turnstile.hostname'));
        } catch (\Throwable $exception) {
            // Never log the token, secret, email or request body.
            Log::warning('Validasi CAPTCHA login tidak tersedia.', [
                'exception_class' => get_class($exception),
            ]);
            return false;
        }
    }
}
