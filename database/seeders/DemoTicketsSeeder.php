<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoTicketsSeeder extends Seeder
{
    /**
     * Tepat 100 tiket simulasi, diidentifikasi dengan external_submission_id.
     * Idempoten: menjalankan ulang tidak mengubah tiket yang sudah dikerjakan.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('Seeder 100 tiket dummy hanya diizinkan pada APP_ENV=local.');
            return;
        }

        $verifiers = User::query()->where('role', 'verifier')->orderBy('id')->get();
        if ($verifiers->isEmpty()) {
            $this->command?->warn('Buat akun verifikator dahulu melalui php artisan db:seed.');
            return;
        }

        $services = ['Reset Passphrase', 'Pembaruan', 'Pendaftaran'];
        $units = ['Unit Kerja Simulasi A', 'Unit Kerja Simulasi B', 'Unit Kerja Simulasi C', 'Unit Kerja Simulasi D'];
        $inserted = 0;
        for ($i = 1; $i <= 100; $i++) {
            $externalId = sprintf('TTE-SIMULASI-%03d', $i);
            if (Ticket::where('external_submission_id', $externalId)->exists()) {
                continue;
            }

            // 55 antrean, 30 diproses, 15 selesai. Tidak ada data pribadi nyata.
            $status = $i <= 55 ? 'Open' : ($i <= 85 ? 'In Progress' : 'Done');
            $verifier = $status === 'Open' ? null : $verifiers[($i - 1) % $verifiers->count()];
            $createdAt = now()->subDays((100 - $i) % 20)->subMinutes(($i * 17) % 720);
            $completedAt = $status === 'Done' ? $createdAt->copy()->addHours(($i % 6) + 1) : null;
            $ticket = Ticket::create([
                'external_submission_id' => $externalId,
                'nama_user' => sprintf('Pemohon Simulasi %03d', $i),
                // Prefix operator 800 sengaja tidak valid agar tidak menyerupai nomor pelanggan asli.
                'no_wa' => sprintf('628000001%04d', $i),
                'layanan' => $services[($i - 1) % 3],
                'status' => $status,
                'wa_verification_status' => $status === 'Done' ? 'verified' : ($i % 5 === 0 ? 'unverified' : ($status === 'In Progress' ? 'verified' : 'unchecked')),
                'source' => 'simulated',
                'opd' => $units[($i - 1) % count($units)],
                'jabatan' => 'Jabatan Simulasi',
                'email' => sprintf('simulasi%03d@example.test', $i),
                'keterangan' => 'DATA DUMMY UNTUK PENGUJIAN — bukan permohonan layanan sebenarnya.',
                'claimed_by' => $verifier?->id,
                'claimed_at' => $verifier ? $createdAt->copy()->addMinutes(20) : null,
                'completed_at' => $completedAt,
                'resolution_note' => $completedAt ? 'SIMULASI: proses layanan selesai untuk pengujian alur aplikasi.' : null,
                'created_at' => $createdAt,
                'updated_at' => $completedAt ?? $createdAt,
            ]);
            $ticket->update([
                'ticket_number' => sprintf('TTE-SIM-%06d', $i),
                'updated_at' => $completedAt ?? $createdAt,
            ]);

            if ($verifier) {
                TicketActivity::create([
                    'ticket_id' => $ticket->id, 'actor_id' => $verifier->id,
                    'action' => 'claimed', 'created_at' => $createdAt->copy()->addMinutes(20),
                    'updated_at' => $createdAt->copy()->addMinutes(20),
                ]);
            }
            if ($completedAt) {
                TicketActivity::create([
                    'ticket_id' => $ticket->id, 'actor_id' => $verifier->id,
                    'action' => 'completed', 'note' => $ticket->resolution_note,
                    'created_at' => $completedAt, 'updated_at' => $completedAt,
                ]);
            }
            $inserted++;
        }

        $this->command?->info("Tiket simulasi baru: {$inserted}. Total beridentitas TTE-SIMULASI-*: ".Ticket::where('external_submission_id', 'like', 'TTE-SIMULASI-%')->count());
    }
}
