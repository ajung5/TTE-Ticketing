<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\WaMessageTemplate;

class WhatsAppMessage {
    public function forUnverifiedTicket(Ticket $ticket): string {
        return $this->render($ticket, 'unverified');
    }

    public function forFollowUpTicket(Ticket $ticket): string {
        return $this->render($ticket, 'followup');
    }

    public function forInboxReminderTicket(Ticket $ticket): string {
        return $this->render($ticket, 'inbox_reminder');
    }

    public function forCompletedTicket(Ticket $ticket): string {
        return $this->render($ticket, 'completed');
    }

    private function render(Ticket $ticket, string $event): string {
        $template = WaMessageTemplate::query()
            ->where('service_code', $ticket->layanan)
            ->where('event_type', $event)
            ->first();

        if (!$template) {
            // Fallback ketika jenis layanan belum memiliki template (misalnya data legacy).
            $templateBody =
                "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan {layanan} dengan nomor tiket {nomor_tiket} " .
                "sedang ditangani oleh layanan TTE.\n\nPetugas: {nama_verifikator} ({inisial_verifikator})\n\n{nama_instansi}";
        } else {
            $templateBody = $template->body;
        }

        $verifier = $ticket->claimant;

        return strtr($templateBody, [
            '{nama_pemohon}' => (string) $ticket->nama_user,
            '{nomor_tiket}' => (string) ($ticket->ticket_number ?? '#' . $ticket->id),
            '{layanan}' => $ticket->service_label,
            '{nama_verifikator}' => (string) ($verifier?->name ?? 'Tim Verifikator TTE'),
            '{inisial_verifikator}' => (string) ($verifier?->initials ?: '-'),
            '{nama_instansi}' => (string) config('tte.service_name', 'Layanan TTE'),
            '{opd}' => (string) ($ticket->opd ?: '-'),
            '{email}' => (string) ($ticket->email ?: '-'),
            '{nik}' => $ticket->masked_nik,
            '{nip}' => (string) ($ticket->nip ?: '-'),
            '{nomor_wa}' => (string) ($ticket->no_wa ?: '-'),
            '{jabatan}' => (string) ($ticket->jabatan ?: '-'),
            '{keterangan}' => (string) ($ticket->keterangan ?: '-'),
            '{status_verifikasi_wa}' => $ticket->wa_verification_label
        ]);
    }

    public function url(Ticket $ticket, string $message): string {
        return 'https://web.whatsapp.com/send?phone=' . $ticket->no_wa . '&text=' . rawurlencode($message);
    }
}
