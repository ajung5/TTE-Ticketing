<?php

namespace App\Providers;

use App\Models\Ticket;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
    public function register(): void {}

    public function boot(): void {
        // Gunakan pagination milik aplikasi, bukan template Tailwind bawaan Laravel.
        // Berlaku untuk Antrean Tiket, Hasil Survei, dan Laporan Tiket.
        Paginator::defaultView('pagination.tte');

        // Respons khusus survei tetap membawa status 429 dan header Retry-After.
        $surveyLimitResponse = function (Request $request, array $headers) {
            $retryAfter = max(1, (int) ($headers['Retry-After'] ?? 60));
            $message = 'Batas akses survei tercapai. Silakan coba lagi setelah ' . $retryAfter . ' detik.';
            $headers['Cache-Control'] = 'no-store, private';
            $headers['X-Robots-Tag'] = 'noindex, nofollow';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'retry_after' => $retryAfter], 429, $headers);
            }

            if ($request->hasSession()) {
                if ($request->isMethod('post')) {
                    // Simpan hanya isian survei, tanpa token atau input lain.
                    $request->flashOnly([
                        'respondent_name',
                        'overall_rating',
                        'staff_rating',
                        'clarity_rating',
                        'speed_rating',
                        'feedback'
                    ]);
                } else {
                    $request->session()->keep('_old_input');
                }
            }

            return response()->view(
                'public.survey-rate-limited',
                [
                    'retryAfter' => $retryAfter,
                    'retryUrl' => $request->fullUrl(),
                    'isSubmission' => $request->isMethod('post')
                ],
                429,
                $headers
            );
        };

        RateLimiter::for('tte-survey-view', function (Request $request) use ($surveyLimitResponse) {
            // Throttle dapat berjalan sebelum middleware signed/binding.
            // Tautan tidak valid dibatasi terpisah agar tidak menghabiskan kuota tiket.
            if (!$request->hasValidSignature()) {
                return Limit::perMinute(20)->by('invalid-ip:' . $request->ip());
            }
            $ticket = $request->route('ticket');
            $ticketId = $ticket instanceof Ticket ? $ticket->getKey() : $ticket;

            return Limit::perMinute(20)
                ->by('ticket:' . $ticketId . '|ip:' . $request->ip())
                ->response($surveyLimitResponse);
        });

        RateLimiter::for('tte-survey-submit', function (Request $request) use ($surveyLimitResponse) {
            if (!$request->hasValidSignature()) {
                return Limit::perMinutes(10, 5)->by('invalid-ip:' . $request->ip());
            }
            $ticket = $request->route('ticket');
            $ticketId = $ticket instanceof Ticket ? $ticket->getKey() : $ticket;

            // Nama limiter memisahkan POST dari GET/rute lain; kuota per tiket
            // tidak terbagi dengan pemohon lain pada jaringan/IP yang sama.
            return Limit::perMinutes(10, 5)
                ->by('ticket:' . $ticketId)
                ->response($surveyLimitResponse);
        });

        RateLimiter::for('tte-password-email', function (Request $request) {
            $emailHash = hash('sha256', strtolower(trim((string) $request->input('email', ''))));
            return [
                Limit::perMinutes(10, 12)->by('ip:' . $request->ip()),
                Limit::perMinutes(10, 3)->by('email:' . $emailHash),
            ];
        });

        RateLimiter::for('tte-nik-lookup', function (Request $request) {
            return [
                Limit::perMinute(6)->by('minute:' . $request->ip()),
                Limit::perDay(120)->by('day:' . $request->ip())
            ];
        });
    }
}
