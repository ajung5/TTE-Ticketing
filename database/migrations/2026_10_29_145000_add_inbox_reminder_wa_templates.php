<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $instructions = [
            'Reset Passphrase' => 'Silakan periksa Inbox atau Spam email dinas {email} untuk melihat apakah ada instruksi resmi terkait reset passphrase. Jika belum ada, sampaikan kepada petugas.',
            'Pembaruan' => 'Silakan periksa Inbox atau Spam email dinas {email} untuk melihat apakah ada informasi resmi pembaruan Sertifikat Elektronik. Jika belum ada, sampaikan kepada petugas.',
            'Pendaftaran' => 'Silakan periksa Inbox atau Spam email dinas {email} untuk melihat apakah ada informasi resmi pendaftaran atau aktivasi Sertifikat Elektronik. Jika belum ada, sampaikan kepada petugas.',
        ];

        foreach ($instructions as $service => $instruction) {
            DB::table('wa_message_templates')->insertOrIgnore([
                'service_code' => $service,
                'event_type' => 'inbox_reminder',
                'body' => "Yth. Bapak/Ibu {nama_pemohon},\n\nTerkait permohonan *{layanan}* nomor tiket *{nomor_tiket}*, kami mengingatkan untuk memeriksa email dinas.\n\n"
                    .$instruction."\n\nPemberitahuan ini bukan konfirmasi bahwa BSrE telah mengirimkan email.\n\n"
                    ."Petugas: {nama_verifikator} ({inisial_verifikator})\n{nama_instansi}\n\n"
                    .'Jangan membagikan password, OTP, atau passphrase kepada siapa pun.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('wa_message_templates')->where('event_type', 'inbox_reminder')->delete();
    }
};
