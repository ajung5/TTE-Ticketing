<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('activation_status', 20)->nullable();
            $table->string('certificate_issuance_status', 20)->nullable();
            $table->string('recommended_service', 40)->nullable();
        });

        // Mempertahankan arti hasil pemeriksaan AMS terdahulu, tanpa menghapus histori.
        DB::table('tickets')->whereNotNull('wa_checked_at')->where('certificate_status', 'active')
            ->update(['activation_status' => 'activated', 'certificate_issuance_status' => 'issued']);
        DB::table('tickets')->whereNotNull('wa_checked_at')->where('certificate_status', 'inactive')
            ->update(['activation_status' => 'not_activated']);

        $presets = [
            ['certificate_not_issued', "Yth. Bapak/Ibu {nama_pemohon},\n\nBerdasarkan pemeriksaan AMS BSrE, akun Anda sudah diaktivasi, tetapi Sertifikat Elektronik belum terbit. Silakan periksa Inbox/Spam email dinas {email}, lalu lakukan set passphrase melalui petunjuk resmi BSrE. Setelah selesai, hubungi petugas untuk pemeriksaan ulang.\n\nNomor tiket: {nomor_tiket}\nPetugas: {nama_verifikator}\n{nama_instansi}\n\nJangan membagikan passphrase, OTP, atau password."],
            ['service_mismatch', "Yth. Bapak/Ibu {nama_pemohon},\n\nBerdasarkan pemeriksaan AMS BSrE, jenis layanan yang Anda ajukan belum sesuai dengan kondisi akun.\n\nLayanan diajukan: {layanan_diajukan}\nLayanan disarankan: {layanan_disarankan}\n\nSilakan mengajukan permohonan baru melalui: {form_url}\n\nPermohonan sebelumnya ditutup secara administratif dan bukan penyelesaian layanan berhasil.\n\nNomor tiket: {nomor_tiket}\nPetugas: {nama_verifikator}\n{nama_instansi}"],
        ];
        foreach ($presets as [$event, $body]) {
            // Tidak menyentuh enam template lama maupun kustomisasi admin.
            DB::table('wa_message_templates')->insertOrIgnore([
                'service_code' => '*',
                'event_type' => $event,
                'body' => $body,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Rollback hanya menghapus dua preset yang dibuat fitur ini.
        DB::table('wa_message_templates')->where('service_code', '*')
            ->whereIn('event_type', ['certificate_not_issued', 'service_mismatch'])->delete();
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['activation_status', 'certificate_issuance_status', 'recommended_service']);
        });
    }
};
