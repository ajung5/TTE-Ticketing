<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('certificate_status', 20)->default('unchecked');
        });

        $presets = [
            ['*', 'unverified', "Yth. Bapak/Ibu {nama_pemohon},\n\nBerdasarkan pemeriksaan AMS BSrE, nomor WhatsApp akun Anda belum terverifikasi. Mohon lakukan verifikasi WA pada AMS BSrE sesuai petunjuk resmi.\n\nNomor tiket: {nomor_tiket}\nPetugas: {nama_verifikator}\n{nama_instansi}\n\nJangan bagikan OTP atau password."],
            ['Reset Passphrase', 'reset', "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan Reset Passphrase dengan nomor tiket {nomor_tiket} telah diproses. Silakan periksa Inbox atau Spam email dinas {email} untuk petunjuk resmi BSrE.\n\nPetugas: {nama_verifikator}\n{nama_instansi}\n\nJangan bagikan passphrase, OTP, atau password."],
            ['Pembaruan', 'renewal', "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan Pembaruan Sertifikat Elektronik nomor tiket {nomor_tiket} telah diproses. Silakan periksa Inbox atau Spam email dinas {email} untuk tahapan pembaruan dari BSrE.\n\nPetugas: {nama_verifikator}\n{nama_instansi}"],
            ['Pendaftaran', 'registration', "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan Pendaftaran TTE nomor tiket {nomor_tiket} telah diproses. Mohon menunggu informasi tahapan berikutnya sesuai ketentuan BSrE.\n\nPetugas: {nama_verifikator}\n{nama_instansi}"],
            ['*', 'completed', "Yth. Bapak/Ibu {nama_pemohon},\n\nPenanganan permohonan {layanan} dengan nomor tiket {nomor_tiket} telah selesai.\n\nMohon berkenan mengisi survei kepuasan layanan berikut: {survey_url}\n\nPetugas: {nama_verifikator}\n{nama_instansi}"],
            ['*', 'unregistered', "Yth. Bapak/Ibu {nama_pemohon},\n\nBerdasarkan pengecekan AMS BSrE, sertifikat elektronik aktif untuk akun Anda belum ditemukan. Mohon hubungi petugas untuk konfirmasi dan tindak lanjut permohonan {layanan}.\n\nNomor tiket: {nomor_tiket}\nPetugas: {nama_verifikator}\n{nama_instansi}"],
        ];
        foreach ($presets as [$service, $event, $body]) {
            DB::table('wa_message_templates')->insertOrIgnore([
                'service_code' => $service,
                'event_type' => $event,
                'body' => $body,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        // Preset lama tidak dihapus: menjaga kustomisasi historis milik admin.
    }

    public function down(): void {
        DB::table('wa_message_templates')->where(function ($query) {
            $query->whereIn('event_type', ['reset','renewal','registration','unregistered'])
                ->orWhere(function ($q) {
                    $q->where('service_code', '*')->whereIn('event_type', ['unverified','completed']);
                });
        })->delete();
        Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn('certificate_status'));
    }
};
