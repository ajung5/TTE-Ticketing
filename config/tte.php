<?php

return [
    'service_name' => env('TTE_SERVICE_NAME', 'Layanan TTE Kabupaten Subang'),
    'new_ticket_inbox_email' => env('TTE_NEW_TICKET_INBOX_EMAIL'),
    'turnstile' => [
        'site_key' => env('TTE_TURNSTILE_SITE_KEY'),
        'secret_key' => env('TTE_TURNSTILE_SECRET_KEY'),
        'hostname' => env('TTE_TURNSTILE_HOSTNAME', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
    ],
    // Versi rilis hanya dari berkas VERSION di root Git, bukan label paket/override .env.
    'version' => trim((string) file_get_contents(base_path('VERSION'))),
];
