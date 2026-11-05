<?php

namespace App\Services;

use App\Models\Ticket;
use App\Notifications\NewTicketCreatedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Satu pesan email untuk satu mailbox operasional, bukan ke akun verifikator.
 * Dijalankan setelah transaksi pembuatan tiket berhasil commit.
 * Jika SMTP/queue gagal, tiket yang sudah dibuat tetap tersimpan.
 */
class NewTicketEmailNotifier {
    public function notify(Ticket $ticket): void {
        if ($ticket->status !== 'Open') {
            return;
        }

        $inbox = trim((string) config('tte.new_ticket_inbox_email', ''));
        if (!filter_var($inbox, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Notifikasi tiket baru dilewati: alamat mailbox belum dikonfigurasi atau tidak valid.', [
                'ticket_id' => $ticket->getKey()
            ]);
            return;
        }

        try {
            $receivedAt = $ticket->created_at
                ? $ticket->created_at->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i')
                : now('Asia/Jakarta')->format('d/m/Y H:i');

            // Notification implements ShouldQueue: satu job email ke SATU alamat.
            // Isi tidak menyertakan NIK, NIP, nomor WA, atau tanda tangan.
            Notification::route('mail', $inbox)->notify(
                new NewTicketCreatedNotification(
                    (string) ($ticket->ticket_number ?? '#' . $ticket->id),
                    (string) $ticket->service_label,
                    $receivedAt
                )
            );
        } catch (\Throwable $e) {
            Log::error('Gagal mengantrikan email ke mailbox tiket.', [
                'ticket_id' => $ticket->getKey(),
                'exception_class' => get_class($e)
            ]);
        }
    }
}
