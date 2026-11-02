<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // INSERT saja: semua preset lama dan kustomisasi admin tetap utuh.
        DB::table('wa_message_templates')->insertOrIgnore([
            'service_code' => '*',
            'event_type' => 'issuance_required',
            'body' => 'Yth. Bapak/Ibu {nama_pemohon},

Berdasarkan pemeriksaan AMS BSrE, Sertifikat Elektronik Anda belum pernah terbit, meskipun akun sudah diaktivasi atau nomor WhatsApp sudah terverifikasi. Layanan Reset Passphrase atau Pembaruan belum sesuai untuk kondisi ini.

Layanan diajukan: {layanan_diajukan}
Layanan disarankan: {layanan_disarankan}

Mohon ajukan permohonan Penerbitan Sertifikat Elektronik melalui: {form_url}
Petugas akan mengembalikan tiket ini dengan status Perlu Pengajuan Ulang setelah konfirmasi pesan. Tiket ini tidak dihitung sebagai layanan selesai.

Nomor tiket: {nomor_tiket}
Petugas: {nama_verifikator}
{nama_instansi}

Jangan membagikan passphrase, OTP, atau password.',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function down(): void {
        // Pertahankan preset dan kustomisasi admin, termasuk saat rollback.
        // Kode lama mengabaikan event yang belum dikenalnya.
    }
};
