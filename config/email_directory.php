<?php

// Admin cukup mengisi nomor WhatsApp di .env, misalnya 6281234567890 atau 081234567890.
// Tidak ada nomor default: tautan hanya aktif ketika kontak resmi telah dikonfigurasi.
$contactNumber = trim((string) env('TTE_EMAIL_ADMIN_CONTACT_WA', ''));
$contactNumber = preg_replace('/\D+/', '', $contactNumber);

if (str_starts_with($contactNumber, '0')) {
    $contactNumber = '62'.substr($contactNumber, 1);
}

$contactUrl = null;
if (preg_match('/^628[0-9]{7,12}$/D', $contactNumber)) {
    $message = 'Halo Pengelola Email Dinas, saya memerlukan bantuan terkait akun Email Gov untuk pengajuan layanan TTE.';
    $contactUrl = 'https://wa.me/'.$contactNumber.'?text='.rawurlencode($message);
} else {
    // Kompatibilitas dengan konfigurasi pada versi sebelumnya.
    $legacyContactUrl = env('TTE_EMAIL_ADMIN_CONTACT_URL');
    if (is_string($legacyContactUrl) && preg_match('~^https://~i', $legacyContactUrl)) {
        $contactUrl = $legacyContactUrl;
    }
}

return [
    'spreadsheet_id' => env('TTE_EMAIL_SHEET_ID'),
    'range' => env('TTE_EMAIL_SHEET_RANGE', 'Data!A1:R'),
    // Lokasi JSON service account sebaiknya berada di luar repository dan folder public/.
    'service_account_json' => env('TTE_GOOGLE_SERVICE_ACCOUNT_JSON'),
    'admin_contact_url' => $contactUrl,
    'max_age_hours' => (int) env('TTE_EMAIL_DIRECTORY_MAX_AGE_HOURS', 168),
];
