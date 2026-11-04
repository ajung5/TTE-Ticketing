<?php

namespace Database\Seeders;

use App\Models\WaMessageTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Preset yang dibutuhkan aplikasi, bukan data pemohon. Aman dijalankan ulang. */
class WaTemplatePresetsSeeder extends Seeder
{
    public function run(): void
    {
        $messages = [
            'unverified' => "Yth. Bapak/Ibu {nama_pemohon},\n\nNomor WhatsApp Anda belum terverifikasi pada AMS BSrE. Silakan lakukan verifikasi sebelum melanjutkan layanan.\nTiket: {nomor_tiket}\nPetugas: {nama_verifikator}",
            'reset' => "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan Reset Passphrase nomor tiket {nomor_tiket} telah diproses. Periksa inbox/spam email dinas {email} untuk instruksi BSrE. Jangan bagikan passphrase.\nPetugas: {nama_verifikator}",
            'renewal' => "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan Pembaruan Sertifikat Elektronik nomor tiket {nomor_tiket} telah diproses. Pantau email dinas {email} untuk informasi resmi BSrE.\nPetugas: {nama_verifikator}",
            'registration' => "Yth. Bapak/Ibu {nama_pemohon},\n\nPendaftaran Sertifikat Elektronik nomor tiket {nomor_tiket} sedang diproses. Mohon pantau email dinas untuk tahapan berikutnya.\nPetugas: {nama_verifikator}",
            'completed' => "Yth. Bapak/Ibu {nama_pemohon},\n\nPenanganan nomor tiket {nomor_tiket} telah selesai. Hasil pelayanan: {hasil_aktual}. Mohon isi survei kepuasan layanan: {survey_url}\nPetugas: {nama_verifikator}",
            'unregistered' => "Yth. Bapak/Ibu {nama_pemohon},\n\nHasil pemeriksaan AMS BSrE menunjukkan akun belum diaktivasi. Mohon ikuti petunjuk pendaftaran dan konfirmasi kembali kepada petugas.\nTiket: {nomor_tiket}\nPetugas: {nama_verifikator}",
            'activation_required' => "Yth. Bapak/Ibu {nama_pemohon},\n\nAkun AMS BSrE Anda sudah terdaftar tetapi belum diaktivasi. Mohon lakukan aktivasi akun sesuai petunjuk BSrE, lalu hubungi petugas untuk pemeriksaan ulang pada nomor tiket {nomor_tiket}.\n\nStatus WhatsApp dan Sertifikat Elektronik akan diperiksa setelah aktivasi. Tidak perlu membuat pengajuan baru hanya karena aktivasi belum selesai.\n\nPetugas: {nama_verifikator}\n{nama_instansi}\n\nJangan membagikan OTP, password, atau passphrase.",
            'certificate_not_issued' => "Yth. Bapak/Ibu {nama_pemohon},\n\nAkun AMS BSrE telah diaktivasi, tetapi Sertifikat Elektronik belum terbit. Periksa inbox/spam email dinas {email}, lakukan set passphrase melalui petunjuk resmi BSrE, lalu hubungi petugas untuk pemeriksaan ulang.\nTiket: {nomor_tiket}\nPetugas: {nama_verifikator}\nJangan pernah membagikan passphrase atau OTP.",
            'certificate_revoked' => "Yth. Bapak/Ibu {nama_pemohon},\n\nSertifikat Elektronik Anda berstatus Revoke (dicabut). Silakan lanjutkan proses Penerbitan Ulang sesuai petunjuk resmi BSrE. Setelah penerbitan ulang, hubungi petugas untuk pemeriksaan AMS ulang pada nomor tiket yang sama: {nomor_tiket}.\n\nLayanan diajukan: {layanan_diajukan}\nProses aktual: {proses_aktual}\nPetugas: {nama_verifikator}\n\nJangan membagikan OTP, password, atau passphrase.",
            'issuance_required' => 'Yth. Bapak/Ibu {nama_pemohon},

Berdasarkan pemeriksaan AMS BSrE, Sertifikat Elektronik Anda belum pernah terbit, meskipun akun sudah diaktivasi atau nomor WhatsApp sudah terverifikasi. Layanan Reset Passphrase atau Pembaruan belum sesuai untuk kondisi ini.

Layanan diajukan: {layanan_diajukan}
Layanan disarankan: {layanan_disarankan}

Mohon ajukan permohonan Penerbitan Sertifikat Elektronik melalui: {form_url}
Petugas akan mengembalikan tiket ini dengan status Perlu Pengajuan Ulang setelah konfirmasi pesan. Tiket ini tidak dihitung sebagai layanan selesai.

Nomor tiket: {nomor_tiket}
Petugas: {nama_verifikator}
{nama_instansi}

Jangan membagikan passphrase, OTP, atau password.',
            'service_mismatch' => "Yth. Bapak/Ibu {nama_pemohon},\n\nJenis layanan {layanan_diajukan} belum sesuai dengan kondisi akun. Setelah klarifikasi, layanan yang disarankan: {layanan_disarankan}. Ajukan melalui {form_url}.\nTiket: {nomor_tiket}\nPetugas: {nama_verifikator}",
        ];

        foreach (WaMessageTemplate::TEMPLATE_SERVICE_CODES as $event => $serviceCode) {
            if (!array_key_exists($event, $messages)) {
                throw new RuntimeException('Preset wajib tidak tersedia: ' . $event);
            }
            DB::table('wa_message_templates')->insertOrIgnore([
                'service_code' => $serviceCode,
                'event_type' => $event,
                'body' => $messages[$event],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
