<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Pemberitahuan email saja; tidak membuat notifikasi dashboard/database. */
class NewTicketCreatedNotification extends Notification implements ShouldQueue {
    use Queueable;

    public int $tries = 3;

    /** Jeda retry (detik) jika SMTP gagal. */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $ticketNumber,
        public readonly string $serviceLabel,
        public readonly string $receivedAtWib
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage {
        return new MailMessage()
            ->subject('[TTE-Ticketing] Tiket baru: ' . $this->ticketNumber)
            ->greeting('Ada permohonan layanan TTE baru')
            ->line('Nomor tiket: ' . $this->ticketNumber)
            ->line('Jenis layanan: ' . $this->serviceLabel)
            ->line('Waktu masuk: ' . $this->receivedAtWib . ' WIB')
            ->line('Status: Belum Diambil')
            ->action('Buka Aplikasi TTE', route('dashboard'))
            ->line(
                'Silakan login untuk melihat informasi terbaru. Tiket diambil oleh verifikator melalui mekanisme self-claim.'
            )
            ->line('Pesan ini bersifat pemberitahuan, bukan penugasan tiket.');
    }
}
