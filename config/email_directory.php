<?php

return [
    'spreadsheet_id' => env('TTE_EMAIL_SHEET_ID'),
    'range' => env('TTE_EMAIL_SHEET_RANGE', 'Data!A1:R'),
    'service_account_json' => env('TTE_GOOGLE_SERVICE_ACCOUNT_JSON'),
    // Nomor WA petugas (08... atau 628...). Disimpan sebagai nomor, bukan URL.
    'admin_contact_wa' => env('TTE_EMAIL_ADMIN_CONTACT_WA', ''),
    'max_age_hours' => (int) env('TTE_EMAIL_DIRECTORY_MAX_AGE_HOURS', 168),
];
