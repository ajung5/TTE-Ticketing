<?php

return [
    'spreadsheet_id' => env('TTE_EMAIL_SHEET_ID'),
    'range' => env('TTE_EMAIL_SHEET_RANGE', 'Data!A1:R'),
    // Absolute path outside public/ and outside the repository is recommended.
    'service_account_json' => env('TTE_GOOGLE_SERVICE_ACCOUNT_JSON'),
    'admin_contact_url' => env('TTE_EMAIL_ADMIN_CONTACT_URL'),
    'max_age_hours' => (int) env('TTE_EMAIL_DIRECTORY_MAX_AGE_HOURS', 168)
];
