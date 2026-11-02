<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Default: hanya baseline operasional; tidak pernah membuat tiket contoh.
 * Pengujian demo hanya dengan php artisan db:seed --class=DemoTicketsSeeder.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TteBaselineSeeder::class);
    }
}
