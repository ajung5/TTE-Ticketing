<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\WaMessageTemplate;
use Illuminate\Support\Facades\URL;

class WhatsAppMessage {
    public function forServiceTicket(Ticket $ticket, string $event): string {
        if (!array_key_exists($event, WaMessageTemplate::TEMPLATE_SERVICE_CODES) || $event === 'completed') {
            throw new \InvalidArgumentException('Jenis notifikasi tidak valid.');
        }
        return $this->render($ticket, $event);
    }

    public function forCompletedTicket(Ticket $ticket): string {
        $message = $this->render($ticket, 'completed');
        // Tautan survei harus ada walaupun preset admin lama tidak memuat {survey_url}.
        if (!str_contains($message, '/survei/'.$ticket->getKey().'?')) {
            $message .= "\n\nSurvei Kepuasan Layanan (30 hari):\n".$this->surveyUrl($ticket);
        }
        return $message;
    }

    public function surveyUrl(Ticket $ticket): string {
        return URL::temporarySignedRoute(
            'public.survey.show',
            now()->addDays(30),
            ['ticket' => $ticket->getKey()]
        );
    }

    private function render(Ticket $ticket, string $event): string {
        $serviceCode = WaMessageTemplate::TEMPLATE_SERVICE_CODES[$event];
        $template = WaMessageTemplate::query()
            ->where('service_code', $serviceCode)
            ->where('event_type', $event)
            ->first();

        $fallback = [
            'unverified' => "Yth. Bapak/Ibu {nama_pemohon},\n\nNomor WA Anda belum terverifikasi pada AMS BSrE. Mohon lakukan verifikasi sebelum layanan dilanjutkan.\nTiket: {nomor_tiket}\nPetugas: {nama_verifikator}",
            'unregistered' => "Yth. Bapak/Ibu {nama_pemohon},\n\nBerdasarkan pemeriksaan AMS BSrE, sertifikat elektronik aktif belum ditemukan untuk NIK Anda. Silakan hubungi petugas untuk tindak lanjut.\nTiket: {nomor_tiket}\nPetugas: {nama_verifikator}",
            'reset' => "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan Reset Passphrase nomor tiket {nomor_tiket} telah diproses. Silakan periksa Inbox/Spam pada email dinas {email} untuk instruksi resmi BSrE.\nPetugas: {nama_verifikator}",
            'renewal' => "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan Pembaruan Sertifikat Elektronik nomor tiket {nomor_tiket} telah diproses. Silakan periksa Inbox/Spam email dinas {email} untuk informasi pembaruan BSrE.\nPetugas: {nama_verifikator}",
            'registration' => "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan Pendaftaran TTE nomor tiket {nomor_tiket} telah diproses. Mohon tunggu informasi tahapan berikutnya.\nPetugas: {nama_verifikator}",
            'completed' => "Yth. Bapak/Ibu {nama_pemohon},\n\nPenanganan permohonan {layanan} nomor tiket {nomor_tiket} telah selesai. Mohon mengisi survei kepuasan melalui {survey_url}.\nPetugas: {nama_verifikator}",
        ];
        $templateBody = $template?->body ?? $fallback[$event];
        $verifier = $ticket->claimant;

        return strtr($templateBody, [
            '{nama_pemohon}' => (string) $ticket->nama_user,
            '{nomor_tiket}' => (string) ($ticket->ticket_number ?? '#'.$ticket->id),
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
            '{status_verifikasi_wa}' => $ticket->wa_verification_label,
            '{survey_url}' => $event === 'completed' ? $this->surveyUrl($ticket) : '-',
        ]);
    }

    public function url(Ticket $ticket, string $message): string {
        return 'https://web.whatsapp.com/send?phone=' . $ticket->no_wa . '&text=' . rawurlencode($message);
    }
}
