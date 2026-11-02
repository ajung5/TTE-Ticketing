<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WaMessageTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Seeder NON-DESTRUKTIF: akun petugas dan preset aktif.
 * Tidak menyentuh tiket, audit, direktori email, password akun lama, atau preset kustom.
 */
class TteBaselineSeeder extends Seeder
{
    public function run(): void
    {
        // Gagal pada akun/preset apa pun tidak boleh meninggalkan bootstrap parsial.
        $inserted = DB::transaction(function (): int {
            $this->ensureAccounts();
            return $this->ensurePresets();
        });

        $this->command?->info('Baseline TTE siap: ' . User::where('role', 'admin')->count()
            . ' admin, ' . User::where('role', 'verifier')->count()
            . ' verifikator, ' . WaMessageTemplate::count()
            . ' template WA (' . $inserted . ' preset aktif ditambahkan).');
        $this->command?->info('Tidak ada tiket, data direktori, password pengguna, atau riwayat yang dihapus/ditambah.');
    }

    private function ensureAccounts(): void
    {
        // Untuk DB yang sudah berisi akun petugas, seeder tidak mengubah akun apa pun.
        // Untuk DB BARU: isi email/nama/password bootstrap melalui environment.
        $definitions = [
            ['admin', 'TTE_BASELINE_ADMIN', 'Administrator', 'ADM'],
            ['verifier', 'TTE_BASELINE_VERIFIER1', 'Verifikator 1', 'V1'],
            ['verifier', 'TTE_BASELINE_VERIFIER2', 'Verifikator 2', 'V2'],
        ];

        foreach ($definitions as [$role, $prefix, $defaultName, $defaultInitials]) {
            $email = trim((string) env($prefix . '_EMAIL', ''));
            if ($email === '') {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException($prefix . '_EMAIL tidak valid.');
            }

            $account = User::withTrashed()->where('email', $email)->first();
            if ($account) {
                if ($account->trashed()) {
                    throw new RuntimeException('Akun ' . $email . ' dinonaktifkan; pulihkan secara manual sebelum seeding.');
                }
                if ($account->role !== $role) {
                    throw new RuntimeException('Role akun ' . $email . ' tidak sesuai; tidak diubah otomatis.');
                }
                continue;
            }

            $password = (string) env($prefix . '_PASSWORD', '');
            if (strlen($password) < 12) {
                throw new RuntimeException($prefix . '_PASSWORD harus diisi dengan minimal 12 karakter untuk membuat akun baru.');
            }
            User::create([
                'name' => trim((string) env($prefix . '_NAME', $defaultName)) ?: $defaultName,
                'email' => $email,
                'role' => $role,
                'initials' => trim((string) env($prefix . '_INITIALS', $defaultInitials)) ?: $defaultInitials,
                'password' => Hash::make($password),
            ]);
        }

        if (!User::where('role', 'admin')->exists() || !User::where('role', 'verifier')->exists()) {
            throw new RuntimeException('Baseline memerlukan minimal satu admin dan satu verifikator. '
                . 'Akun belum dibuat: konfigurasi TTE_BASELINE_*_EMAIL dan *_PASSWORD di .env.');
        }
    }

    private function ensurePresets(): int
    {
        $messages = [
            'unverified' => "Yth. Bapak/Ibu {nama_pemohon},\n\nNomor WhatsApp Anda belum terverifikasi pada AMS BSrE. Silakan lakukan verifikasi sebelum melanjutkan layanan.\nTiket: {nomor_tiket}\nPetugas: {nama_verifikator}",
            'reset' => "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan Reset Passphrase nomor tiket {nomor_tiket} telah diproses. Periksa inbox/spam email dinas {email} untuk instruksi BSrE. Jangan bagikan passphrase.\nPetugas: {nama_verifikator}",
            'renewal' => "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan Pembaruan Sertifikat Elektronik nomor tiket {nomor_tiket} telah diproses. Pantau email dinas {email} untuk informasi resmi BSrE.\nPetugas: {nama_verifikator}",
            'registration' => "Yth. Bapak/Ibu {nama_pemohon},\n\nPendaftaran Sertifikat Elektronik nomor tiket {nomor_tiket} sedang diproses. Mohon pantau email dinas untuk tahapan berikutnya.\nPetugas: {nama_verifikator}",
            'completed' => "Yth. Bapak/Ibu {nama_pemohon},\n\nPenanganan permohonan {layanan} nomor tiket {nomor_tiket} telah selesai. Mohon isi survei kepuasan layanan: {survey_url}\nPetugas: {nama_verifikator}",
            'unregistered' => "Yth. Bapak/Ibu {nama_pemohon},\n\nHasil pemeriksaan AMS BSrE menunjukkan akun belum diaktivasi. Mohon ikuti petunjuk pendaftaran dan konfirmasi kembali kepada petugas.\nTiket: {nomor_tiket}\nPetugas: {nama_verifikator}",
            'certificate_not_issued' => "Yth. Bapak/Ibu {nama_pemohon},\n\nAkun AMS BSrE telah diaktivasi, tetapi Sertifikat Elektronik belum terbit. Periksa inbox/spam email dinas {email}, lakukan set passphrase melalui petunjuk resmi BSrE, lalu hubungi petugas untuk pemeriksaan ulang.\nTiket: {nomor_tiket}\nPetugas: {nama_verifikator}\nJangan pernah membagikan passphrase atau OTP.",
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

        $inserted = 0;
        foreach (WaMessageTemplate::TEMPLATE_SERVICE_CODES as $event => $serviceCode) {
            if (!isset($messages[$event])) {
                throw new RuntimeException('Preset wajib belum didefinisikan: ' . $event);
            }
            $inserted += DB::table('wa_message_templates')->insertOrIgnore([
                'service_code' => $serviceCode,
                'event_type' => $event,
                'body' => $messages[$event],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $inserted;
    }
}
