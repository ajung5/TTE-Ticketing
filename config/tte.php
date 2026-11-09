<?php

return [
    'service_name' => env('TTE_SERVICE_NAME', 'Layanan TTE Kabupaten Subang'),
    'new_ticket_inbox_email' => env('TTE_NEW_TICKET_INBOX_EMAIL'),
    // Versi aplikasi semantik, terpisah dari nomor paket patch.
    'version' => env('TTE_APP_VERSION', '1.0.0')
];
