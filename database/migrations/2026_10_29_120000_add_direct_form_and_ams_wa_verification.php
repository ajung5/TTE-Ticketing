<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Value is transparently encrypted by the Ticket model (Laravel encrypted cast).
            $table->text('nik')->nullable();
            $table->string('wa_verification_status', 20)->default('unchecked');
            $table->string('wa_verification_note', 500)->nullable();
            $table->timestamp('wa_checked_at')->nullable();
            $table->foreignId('wa_checked_by')->nullable()->constrained('users')->nullOnDelete();
        });

        foreach (['Reset Passphrase', 'Pembaruan', 'Pendaftaran'] as $service) {
            DB::table('wa_message_templates')->insertOrIgnore([
                'service_code' => $service,
                'event_type' => 'unverified',
                'body' => "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan *{layanan}* nomor tiket *{nomor_tiket}* telah kami periksa. "
                    ."Berdasarkan pemeriksaan petugas pada AMS BSrE, nomor WhatsApp Anda saat ini *belum terverifikasi*.\n\n"
                    ."Mohon lakukan verifikasi nomor WhatsApp melalui akun AMS BSrE sesuai petunjuk resmi. "
                    ."Jika mengalami kendala, hubungi petugas untuk pendampingan. Kami akan memeriksa kembali setelah verifikasi selesai.\n\n"
                    ."Petugas: {nama_verifikator} ({inisial_verifikator})\n{nama_instansi}\n\n"
                    .'Jangan membagikan OTP, password, atau passphrase kepada siapa pun.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('wa_message_templates')->where('event_type', 'unverified')->delete();
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wa_checked_by');
            $table->dropColumn(['nik', 'wa_verification_status', 'wa_verification_note', 'wa_checked_at']);
        });
    }
};
