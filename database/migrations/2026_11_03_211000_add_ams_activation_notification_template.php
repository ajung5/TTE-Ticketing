<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Menjaga seluruh template lama dan kustomisasi admin.
        DB::table('wa_message_templates')->insertOrIgnore([
            'service_code' => '*',
            'event_type' => 'activation_required',
            'body' => "Yth. Bapak/Ibu {nama_pemohon},

Berdasarkan pemeriksaan AMS BSrE, akun Anda sudah terdaftar tetapi belum diaktivasi. Mohon melakukan aktivasi akun sesuai petunjuk resmi BSrE, kemudian beri tahu petugas untuk pemeriksaan ulang pada nomor tiket {nomor_tiket}.

Anda tidak perlu membuat permohonan baru hanya karena status aktivasi belum selesai. Verifikasi WhatsApp dan penerbitan sertifikat akan diperiksa setelah aktivasi.

Petugas: {nama_verifikator}
{nama_instansi}

Jangan membagikan OTP, password, atau passphrase.",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void {
        // Tidak menghapus template yang mungkin sudah dikustomisasi admin.
    }
};
