<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::table('wa_message_templates')->insertOrIgnore([
            'service_code' => '*',
            'event_type' => 'certificate_revoked',
            'body' => "Yth. Bapak/Ibu {nama_pemohon},\n\nBerdasarkan pemeriksaan AMS BSrE, Sertifikat Elektronik Anda berstatus Revoke (telah dicabut). Sertifikat tersebut tidak dapat digunakan kembali. Silakan mengikuti proses Penerbitan Ulang Sertifikat Elektronik sesuai petunjuk resmi BSrE.\n\nLayanan awal: {layanan_diajukan}\nProses aktual: {proses_aktual}\nNomor tiket: {nomor_tiket}\nPetugas: {nama_verifikator}\n\nSetelah penerbitan ulang selesai, hubungi petugas untuk pemeriksaan AMS ulang. Tiket ini tetap digunakan selama proses masih dapat ditindaklanjuti. Jangan memberikan OTP, password, atau passphrase kepada siapa pun.",
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function down(): void {
        // Menjaga pesan yang mungkin sudah dikustomisasi admin.
    }
};
