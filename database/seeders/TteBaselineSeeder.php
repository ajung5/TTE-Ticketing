<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WaMessageTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/** Bootstrap tidak destruktif: akun petugas + preset aktif, tanpa tiket/survei dummy. */
class TteBaselineSeeder extends Seeder {
    public function run(): void {
        DB::transaction(function (): void {
            $this->ensureAccounts();
            $this->call(WaTemplatePresetsSeeder::class);
        });
        $this->command?->info(
            'Baseline siap: ' .
                User::where('role', 'admin')->count() .
                ' admin, ' .
                User::where('role', 'verifier')->count() .
                ' verifikator, ' .
                WaMessageTemplate::count() .
                ' preset WA.'
        );
        $this->command?->info('Tiket, survei, direktori, password akun lama, dan template kustom tetap utuh.');
    }

    private function ensureAccounts(): void {
        $definitions = [
            ['admin', 'TTE_BASELINE_ADMIN', 'Administrator', 'ADM'],
            ['verifier', 'TTE_BASELINE_VERIFIER1', 'Verifikator 1', 'V1'],
            ['verifier', 'TTE_BASELINE_VERIFIER2', 'Verifikator 2', 'V2']
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
                    throw new RuntimeException(
                        'Akun ' . $email . ' dinonaktifkan; pulihkan secara manual sebelum seeding.'
                    );
                }
                if ($account->role !== $role) {
                    throw new RuntimeException('Role akun ' . $email . ' tidak sesuai; tidak diubah otomatis.');
                }
                continue;
            }
            $password = (string) env($prefix . '_PASSWORD', '');
            if (strlen($password) < 12) {
                throw new RuntimeException(
                    $prefix . '_PASSWORD harus diisi dengan minimal 12 karakter untuk membuat akun baru.'
                );
            }
            User::create([
                'name' => trim((string) env($prefix . '_NAME', $defaultName)) ?: $defaultName,
                'email' => $email,
                'role' => $role,
                'initials' => trim((string) env($prefix . '_INITIALS', $defaultInitials)) ?: $defaultInitials,
                'password' => Hash::make($password)
            ]);
        }
        if (!User::where('role', 'admin')->exists() || !User::where('role', 'verifier')->exists()) {
            throw new RuntimeException(
                'Baseline memerlukan minimal satu admin dan satu verifikator. ' .
                    'Akun belum dibuat: konfigurasi TTE_BASELINE_*_EMAIL dan *_PASSWORD di .env.'
            );
        }
    }
}
