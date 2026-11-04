<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Bootstrap operasional: hanya akun admin/verifikator dan preset WhatsApp aktif.
 * Direktori pegawai diimpor secara terpisah; tiket dan survei dimulai kosong.
 */
class DatabaseSeeder extends Seeder {
    public function run(): void {
        $this->call(TteBaselineSeeder::class);
    }
}
