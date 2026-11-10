<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WaMessageTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Baseline production tanpa kredensial di .env atau source code.
 * Akun awal dibuat sekali melalui prompt terminal dengan password tersembunyi.
 * Seeder ulang tidak mengubah akun, password, tiket, survei, atau template kustom.
 */
class TteBaselineSeeder extends Seeder {
    public function run(): void {
        // Jangan meminta password saat transaksi database sedang terbuka.
        $pending = [];
        foreach (
            [['admin', 'Administrator', 'ADM'], ['verifier', 'Verifikator 1', 'V1']]
            as [$role, $defaultName, $defaultInitials]
        ) {
            if (User::query()->where('role', $role)->exists()) {
                continue;
            }
            $account = $this->promptAccount($role, $defaultName, $defaultInitials);
            if (in_array($account['email'], array_column($pending, 'email'), true)) {
                throw new RuntimeException('Email akun admin dan verifikator harus berbeda.');
            }
            $pending[] = $account;
        }

        // Hanya hash password yang disimpan. Jika suatu tahap gagal, rollback akun baru
        // dan preset WA dalam transaksi ini; data yang sudah ada tidak tersentuh.
        DB::transaction(function () use ($pending): void {
            foreach ($pending as $account) {
                if (User::query()->where('role', $account['role'])->exists()) {
                    throw new RuntimeException(
                        'Role ' .
                            $account['role'] .
                            ' telah dibuat oleh proses lain. Ulangi seeder.',
                    );
                }
                if (User::withTrashed()->where('email', $account['email'])->exists()) {
                    throw new RuntimeException(
                        'Email sudah digunakan atau pernah dinonaktifkan. Tidak diaktifkan otomatis.',
                    );
                }
                User::create([
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'role' => $account['role'],
                    'initials' => $account['initials'],
                    'password' => Hash::make($account['password']),
                ]);
            }

            if (
                !User::query()->where('role', 'admin')->exists() ||
                !User::query()->where('role', 'verifier')->exists()
            ) {
                throw new RuntimeException(
                    'Baseline memerlukan satu admin dan satu verifikator aktif.',
                );
            }
            $this->call(WaTemplatePresetsSeeder::class);
        });

        $this->command?->info(
            'Baseline siap: ' .
                User::where('role', 'admin')->count() .
                ' admin, ' .
                User::where('role', 'verifier')->count() .
                ' verifikator, ' .
                WaMessageTemplate::count() .
                ' preset WA.',
        );
        $this->command?->info(
            'Akun lama dan password tidak diubah. Tidak ada password di .env atau output terminal.',
        );
        $this->command?->info('Tiket, survei, direktori, dan template WhatsApp kustom tetap utuh.');
    }

    /** @return array{role:string,name:string,email:string,initials:string,password:string} */
    private function promptAccount(
        string $role,
        string $defaultName,
        string $defaultInitials,
    ): array {
        if ($this->command === null) {
            throw new RuntimeException(
                'Akun awal belum ada. Jalankan php artisan db:seed --class=DatabaseSeeder --force ' .
                    'dari terminal interaktif; tidak menggunakan kredensial .env.',
            );
        }

        $this->command->line(
            'Provisioning akun ' . ($role === 'admin' ? 'ADMIN' : 'VERIFIKATOR') . '.',
        );
        $email = strtolower(trim((string) $this->command->ask('Email untuk ' . $role)));
        if (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException(
                'Email tidak valid. Gunakan terminal interaktif (tanpa --no-interaction).',
            );
        }
        if (User::withTrashed()->where('email', $email)->exists()) {
            throw new RuntimeException(
                'Email tersebut sudah ada, termasuk akun nonaktif. Tidak ditimpa.',
            );
        }

        $name = trim((string) $this->command->ask('Nama akun ' . $role, $defaultName));
        $initials = trim((string) $this->command->ask('Inisial ' . $role, $defaultInitials));
        if (
            mb_strlen($name) < 3 ||
            mb_strlen($name) > 160 ||
            !preg_match('/\A[a-zA-Z0-9]{1,12}\z/D', $initials)
        ) {
            throw new RuntimeException(
                'Nama (3-160 karakter) atau inisial (1-12 alfanumerik) tidak valid.',
            );
        }

        $password = (string) $this->command->secret(
            'Password ' . $role . ' (minimal 12 karakter, campuran huruf/angka/simbol)',
        );
        $confirmation = (string) $this->command->secret('Ulangi password ' . $role);
        if (!hash_equals($password, $confirmation)) {
            throw new RuntimeException('Konfirmasi password tidak cocok; akun tidak dibuat.');
        }
        if (
            strlen($password) < 12 ||
            strlen($password) > 72 ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[^a-zA-Z0-9]/', $password)
        ) {
            throw new RuntimeException(
                'Password harus 12-72 byte dan memiliki huruf besar, huruf kecil, angka, serta simbol.',
            );
        }

        return [
            'role' => $role,
            'name' => $name,
            'email' => $email,
            'initials' => $initials,
            'password' => $password,
        ];
    }
}
