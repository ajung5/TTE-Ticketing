<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
    public function register(): void {}

    public function boot(): void {
        RateLimiter::for('tte-nik-lookup', function (Request $request) {
            return [
                Limit::perMinute(6)->by('minute:' . $request->ip()),
                Limit::perDay(120)->by('day:' . $request->ip())
            ];
        });
    }
}
