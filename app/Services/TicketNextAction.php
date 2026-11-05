<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\WaNotification;

/** Read-only presentation, derived from the controller's authoritative AMS decision. */
class TicketNextAction {
    public function resolve(
        Ticket $ticket,
        ?string $event,
        bool $confirmed,
        bool $ready,
        ?WaNotification $serviceNotice,
        ?WaNotification $finalNotice
    ): array {
        $action = static fn(string $title, string $description, ?string $target, string $button, int $step): array => [
            'title' => $title,
            'description' => $description,
            'target' => $target,
            'button' => $button,
            'step' => $step
        ];

        if ($ticket->status === 'Open') {
            return $action(
                'Ambil tiket untuk memulai',
                'Tiket belum memiliki penanggung jawab. Setelah diambil, lakukan pemeriksaan AMS.',
                '#tte-ticket-workflow',
                'Ambil tiket',
                1
            );
        }
        if ($ticket->status === 'Returned') {
            return $action(
                'Perlu pengajuan ulang',
                'Permohonan lama sudah dikembalikan dan tidak dihitung sebagai layanan selesai.',
                null,
                '',
                4
            );
        }
        if ($ticket->status === 'Done') {
            if ($finalNotice?->confirmed_at) {
                return $action(
                    'Penanganan dan notifikasi selesai',
                    'Pesan akhir beserta tautan survei telah dikonfirmasi oleh petugas.',
                    '#tte-final-notification',
                    'Lihat notifikasi akhir',
                    4
                );
            }
            return $finalNotice?->opened_at
                ? $action(
                    'Konfirmasi pesan akhir',
                    'Setelah benar-benar mengirim WhatsApp, lakukan konfirmasi manual.',
                    '#tte-final-notification',
                    'Konfirmasi pesan',
                    4
                )
                : $action(
                    'Kirim notifikasi selesai dan survei',
                    'Tiket sudah selesai. Kirim pesan hasil layanan dan tautan survei ke pemohon.',
                    '#tte-final-notification',
                    'Buka notifikasi akhir',
                    4
                );
        }
        if ($event === null) {
            return $action(
                'Periksa akun di AMS BSrE',
                'Mulai dari status aktivasi akun; tahapan berikutnya akan muncul sesuai hasil pemeriksaan.',
                '#tte-ams-check',
                'Mulai pemeriksaan',
                1
            );
        }
        if ($event === 'ready_for_completion' && $ready) {
            return $action(
                'Tiket siap diselesaikan',
                'Hasil pemeriksaan ulang menunjukkan sertifikat aktif. Pastikan hasil aktual sebelum menutup tiket.',
                '#tte-ticket-workflow',
                'Lanjut ke penyelesaian',
                3
            );
        }
        if ($serviceNotice?->opened_at && !$confirmed) {
            return $action(
                'Konfirmasi pengiriman WhatsApp',
                'Tautan sudah dibuka. Setelah pesan benar-benar dikirim, klik konfirmasi manual.',
                '#tte-service-notification',
                'Lihat konfirmasi pesan',
                2
            );
        }
        if (!$confirmed) {
            return $action(
                'Kirim notifikasi sesuai AMS',
                'Pesan sudah disiapkan sesuai kondisi pemeriksaan terakhir. Periksa isi pesan sebelum membuka WhatsApp.',
                '#tte-service-notification',
                'Lihat pesan WhatsApp',
                2
            );
        }
        if ($ready && in_array($event, ['reset', 'renewal', 'registration'], true)) {
            return $action(
                'Tiket siap diselesaikan',
                'Pastikan hasil pelayanan aktual benar sebelum menutup tiket.',
                '#tte-ticket-workflow',
                'Lanjut ke penyelesaian',
                3
            );
        }
        if ($event === 'registration' && $ticket->layanan === 'Pendaftaran') {
            return $action(
                'Penutupan administratif pendaftaran',
                'Konfirmasi notifikasi Pendaftaran Diproses menutup tiket secara administratif, bukan bukti sertifikat terbit.',
                '#tte-ticket-workflow',
                'Lihat hasil',
                3
            );
        }
        if (in_array($event, ['service_mismatch', 'issuance_required'], true)) {
            return $action(
                'Tindak lanjuti pengajuan ulang',
                'Pesan koreksi sudah dikonfirmasi; pengembalian hanya dilakukan jika proses tidak dapat dilanjutkan pada tiket ini.',
                '#tte-ticket-workflow',
                'Lihat pengembalian',
                3
            );
        }
        return $action(
            'Periksa ulang hasil AMS',
            'Notifikasi tindak lanjut sudah dikonfirmasi. Periksa perkembangan akun/sertifikat menggunakan tiket yang sama.',
            '#tte-ams-check',
            'Pemeriksaan ulang',
            3
        );
    }
}
