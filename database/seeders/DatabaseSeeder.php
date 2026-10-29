<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('Seeder demo hanya berjalan saat APP_ENV=local.');
            return;
        }

        $password = env('TTE_DEMO_PASSWORD', 'TteDemo!2026');
        foreach ([
            ['name' => 'Admin Demo', 'email' => 'admin@tte.test', 'role' => 'admin', 'initials' => 'ADM'],
            ['name' => 'Verifikator A', 'email' => 'verifikator1@tte.test', 'role' => 'verifier', 'initials' => 'VA'],
            ['name' => 'Verifikator B', 'email' => 'verifikator2@tte.test', 'role' => 'verifier', 'initials' => 'VB'],
        ] as $account) {
            User::firstOrCreate(['email' => $account['email']], $account + ['password' => $password]);
        }

        foreach ([
            ['nama_user' => 'Pemohon Contoh Satu', 'no_wa' => '6281200000001', 'layanan' => 'Reset Passphrase', 'opd' => 'OPD Contoh'],
            ['nama_user' => 'Pemohon Contoh Dua', 'no_wa' => '6281200000002', 'layanan' => 'Pembaruan', 'opd' => 'OPD Contoh'],
            ['nama_user' => 'Pemohon Contoh Tiga', 'no_wa' => '6281200000003', 'layanan' => 'Pendaftaran', 'opd' => 'OPD Contoh'],
        ] as $index => $data) {
            $ticket = Ticket::firstOrCreate(['external_submission_id' => 'local-demo-'.($index + 1)],
                $data + ['status' => 'Open', 'source' => 'demo']);
            if (! $ticket->ticket_number) {
                $ticket->update(['ticket_number' => 'TTE-'.now('Asia/Jakarta')->format('Ymd').'-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT)]);
            }
        }

        $this->call(DemoTicketsSeeder::class);
    }
}
