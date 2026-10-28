<?php

namespace App\Services;

use App\Models\Ticket;

class WhatsAppMessage
{
    public function forFollowUpTicket(Ticket $ticket): string
    {
        $verifier = $ticket->claimant;
        $instruction = match ($ticket->layanan) {
            'Reset Passphrase' => 'Permohonan reset passphrase sedang ditindaklanjuti. Mohon memantau email dinas untuk instruksi resmi dari BSrE apabila tersedia.',
            'Pembaruan' => 'Permohonan pembaruan Sertifikat Elektronik sedang ditindaklanjuti. Mohon memantau email dinas untuk informasi langkah selanjutnya.',
            'Pendaftaran' => 'Permohonan pendaftaran TTE baru sedang ditindaklanjuti. Mohon memantau email dinas untuk informasi verifikasi atau aktivasi.',
            default => 'Permohonan Anda sedang ditindaklanjuti.',
        };
        $service = config('tte.service_name');
        $initials = $verifier?->initials ?: '-';

        return "Yth. Bapak/Ibu {$ticket->nama_user},\n\n"
            ."Permohonan *{$ticket->service_label}* dengan nomor tiket *{$ticket->ticket_number}* "
            ."telah ditindaklanjuti oleh Tim Verifikator TTE.\n\n"
            .$instruction."\n\n"
            ."Petugas: {$verifier?->name} ({$initials})\n\n"
            ."Terima kasih.\n{$service}\n\n"
            .'Jangan membagikan passphrase, password, atau OTP kepada siapa pun.';
    }

    public function forCompletedTicket(Ticket $ticket): string
    {
        $verifier = $ticket->claimant;
        $instruction = match ($ticket->layanan) {
            'Reset Passphrase' => 'Silakan memeriksa email dinas untuk instruksi resmi terkait reset passphrase jika sudah diterbitkan.',
            'Pembaruan' => 'Silakan memantau email dinas untuk informasi pembaruan Sertifikat Elektronik.',
            'Pendaftaran' => 'Silakan memantau email dinas untuk informasi tahapan aktivasi TTE.',
            default => 'Silakan memeriksa email dinas untuk informasi lebih lanjut.',
        };
        $service = config('tte.service_name');
        $initials = $verifier?->initials ?: '-';

        return "Yth. Bapak/Ibu {$ticket->nama_user},\n\n"
            ."Permohonan *{$ticket->service_label}* dengan nomor tiket *{$ticket->ticket_number}* "
            ."telah ditandai selesai dalam sistem layanan TTE.\n\n"
            .$instruction."\n\n"
            ."Petugas: {$verifier?->name} ({$initials})\n\n"
            ."Terima kasih.\n{$service}\n\n"
            .'Jangan membagikan passphrase, password, atau OTP kepada siapa pun.';
    }

    public function url(Ticket $ticket, string $message): string
    {
        return 'https://wa.me/'.$ticket->no_wa.'?text='.rawurlencode($message);
    }
}
