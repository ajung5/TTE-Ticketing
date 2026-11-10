<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\WaMessageTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class WhatsAppMessage {
    public function forServiceTicket(Ticket $ticket, string $event): string {
        if (
            !array_key_exists($event, WaMessageTemplate::TEMPLATE_SERVICE_CODES) ||
            $event === 'completed'
        ) {
            throw new \InvalidArgumentException('Jenis notifikasi tidak valid.');
        }
        return $this->render($ticket, $event);
    }

    public function forCompletedTicket(Ticket $ticket): string {
        $message = $this->render($ticket, 'completed');
        if (!str_contains($message, '/survei/' . $ticket->getKey() . '?')) {
            $message .= "\n\nSurvei Kepuasan Layanan (30 hari):\n" . $this->surveyUrl($ticket);
        }
        return $message;
    }

    public function surveyUrl(Ticket $ticket): string {
        // Stable signature: opening the identical final message later must not cancel its confirmation.
        // The 30-day validity starts when the ticket is completed, not on each page view.
        $issuedAt = $ticket->completed_at ?? ($ticket->created_at ?? now());
        return URL::temporarySignedRoute('public.survey.show', $issuedAt->copy()->addDays(30), [
            'ticket' => $ticket->getKey(),
        ]);
    }

    private function render(Ticket $ticket, string $event): string {
        $serviceCode = WaMessageTemplate::TEMPLATE_SERVICE_CODES[$event];
        $template = WaMessageTemplate::query()
            ->where('service_code', $serviceCode)
            ->where('event_type', $event)
            ->first();

        $fallback = [
            'unverified' =>
                "Yth. Bapak/Ibu {nama_pemohon},\n\nNomor WA Anda belum terverifikasi pada AMS BSrE. Mohon lakukan verifikasi sebelum layanan dilanjutkan.\nTiket: {nomor_tiket}\nPetugas: {nama_verifikator}",
            'activation_required' =>
                "Yth. Bapak/Ibu {nama_pemohon},\n\nAkun AMS BSrE Anda belum diaktivasi. Mohon lakukan aktivasi sesuai petunjuk resmi BSrE, kemudian hubungi petugas untuk pemeriksaan ulang pada tiket yang sama: {nomor_tiket}.\n\nJangan membagikan OTP, password, atau passphrase.",
            'unregistered' =>
                "Yth. Bapak/Ibu {nama_pemohon},\n\nBerdasarkan pemeriksaan AMS BSrE, sertifikat elektronik aktif belum ditemukan untuk NIK Anda. Silakan hubungi petugas untuk tindak lanjut.\nTiket: {nomor_tiket}\nPetugas: {nama_verifikator}",
            'reset' =>
                "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan Reset Passphrase nomor tiket {nomor_tiket} telah diproses. Silakan periksa Inbox/Spam pada email dinas {email} untuk instruksi resmi BSrE.\nPetugas: {nama_verifikator}",
            'renewal' =>
                "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan Pembaruan Sertifikat Elektronik nomor tiket {nomor_tiket} telah diproses. Silakan periksa Inbox/Spam email dinas {email} untuk informasi pembaruan BSrE.\nPetugas: {nama_verifikator}",
            'registration' =>
                "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan Pendaftaran TTE nomor tiket {nomor_tiket} telah diproses. Mohon tunggu informasi tahapan berikutnya.\nPetugas: {nama_verifikator}",
            'certificate_not_issued' =>
                "Yth. Bapak/Ibu {nama_pemohon},\n\nAkun AMS BSrE sudah diaktivasi, tetapi Sertifikat Elektronik belum terbit. Silakan periksa Inbox/Spam email dinas {email} dan lakukan set passphrase melalui petunjuk resmi BSrE. Setelah selesai, hubungi petugas untuk pemeriksaan ulang.\n\nTiket: {nomor_tiket}\nPetugas: {nama_verifikator}\nJangan pernah membagikan passphrase, OTP, atau password.",
            'certificate_revoked' =>
                "Yth. Bapak/Ibu {nama_pemohon},\n\nSertifikat Elektronik Anda berstatus Revoke (dicabut). Silakan mengikuti proses Penerbitan Ulang sesuai prosedur resmi BSrE, kemudian beri tahu petugas untuk pemeriksaan ulang.\n\nLayanan diajukan: {layanan_diajukan}\nProses aktual: {proses_aktual}\nTiket yang sama: {nomor_tiket}\nPetugas: {nama_verifikator}\n\nJangan membagikan OTP, password, atau passphrase.",
            'issuance_required' =>
                "Yth. Bapak/Ibu {nama_pemohon},\n\nBerdasarkan pemeriksaan AMS BSrE, SE belum pernah terbit. Ajukan penerbitan melalui {form_url}.\nTiket: {nomor_tiket}\nPetugas: {nama_verifikator}",
            'service_mismatch' =>
                "Yth. Bapak/Ibu {nama_pemohon},\n\nJenis layanan yang diajukan ({layanan_diajukan}) tidak sesuai dengan kondisi akun. Mohon mengajukan kembali dengan memilih {layanan_disarankan} melalui: {form_url}\n\nTiket sebelumnya ditutup secara administratif dan tidak dihitung sebagai layanan berhasil.\nNomor tiket: {nomor_tiket}\nPetugas: {nama_verifikator}",
            'completed' =>
                "Yth. Bapak/Ibu {nama_pemohon},\n\nPenanganan nomor tiket {nomor_tiket} telah selesai. Hasil pelayanan: {hasil_aktual}. Mohon mengisi survei kepuasan melalui {survey_url}.\nPetugas: {nama_verifikator}",
        ];
        $templateBody = $template?->body ?? $fallback[$event];
        if ($event === 'registration' && $ticket->activation_status === 'not_activated') {
            // Tahapan aktivasi disampaikan di dalam notifikasi Pendaftaran Diproses,
            // bukan mengganti jenis notifikasi ataupun mengklaim SE sudah terbit.
            $templateBody .=
                "\n\nBerdasarkan pemeriksaan AMS BSrE, akun Anda belum diaktivasi. " .
                'Mohon lakukan aktivasi akun sesuai petunjuk resmi BSrE agar proses Penerbitan ' .
                'Sertifikat Elektronik dapat dilanjutkan. Setelah aktivasi berhasil, ' .
                'hubungi petugas untuk pemeriksaan ulang. Tidak perlu mengajukan tiket baru.';
        }
        // Preset lama menggunakan {layanan} sebagai nama layanan pengajuan.
        // Untuk tiket baru yang mempunyai hasil aktual, ubah kalimat bakunya
        // agar tidak membentuk frasa rancu seperti 'permohonan sertifikat berhasil terbit'.
        if ($event === 'completed' && $ticket->service_outcome !== null) {
            $templateBody = str_replace(
                'Penanganan permohonan {layanan} nomor tiket {nomor_tiket} telah selesai.',
                'Hasil pelayanan tiket {nomor_tiket}: {hasil_aktual}.',
                $templateBody,
            );
        }
        if (
            $event === 'certificate_not_issued' &&
            $ticket->wa_verification_status === 'unverified'
        ) {
            $templateBody .=
                "\n\nNomor WhatsApp Anda juga belum terverifikasi pada AMS BSrE. Mohon tindak lanjuti verifikasi WhatsApp sebelum penutupan tiket, di samping proses set passphrase.";
        }
        if ($event === 'certificate_revoked' && $ticket->wa_verification_status === 'unverified') {
            $templateBody .=
                "\n\nNomor WhatsApp Anda juga belum terverifikasi. Selesaikan verifikasi WA sebelum penutupan tiket.";
        }
        if ($event === 'unregistered' && $ticket->activation_status === 'not_activated') {
            $templateBody .=
                "\n\nHasil pemeriksaan AMS: akun belum diaktivasi. Mohon lakukan aktivasi akun sesuai petunjuk resmi sebelum proses dilanjutkan.";
        }
        if ($event === 'service_mismatch' && $ticket->certificate_validity_status === 'expired') {
            $templateBody .=
                "\n\nHasil pemeriksaan AMS: sertifikat telah kedaluwarsa. Layanan yang sesuai adalah Pembaruan Sertifikat Elektronik.";
        }
        if ($event === 'renewal' && $ticket->certificate_validity_status === 'expired') {
            $templateBody .=
                "\n\nStatus sertifikat masih kedaluwarsa. Penutupan tiket menunggu pemeriksaan ulang sampai sertifikat aktif.";
        }
        $verifier = $ticket->claimant;
        // Jangan menyebut layanan awal sebagai hasil selesai. Pada tiket historis tanpa outcome,
        // pertahankan perilaku semula agar tidak mengarang hasil yang tidak pernah tercatat.
        $completedLabel = $ticket->service_outcome
            ? $ticket->service_outcome_label
            : $ticket->service_label;
        return strtr($templateBody, [
            '{nama_pemohon}' => (string) $ticket->nama_user,
            '{nomor_tiket}' => (string) ($ticket->ticket_number ?? '#' . $ticket->id),
            '{layanan}' => $event === 'completed' ? $completedLabel : $ticket->service_label,
            '{layanan_diajukan}' => $ticket->service_label,
            '{layanan_disarankan}' => match ($ticket->recommended_service) {
                'Pendaftaran' => 'Penerbitan Sertifikat Elektronik',
                'Pembaruan' => 'Pembaruan Sertifikat Elektronik',
                'Reset Passphrase' => 'Reset Passphrase',
                'Penerbitan Ulang' => 'Penerbitan Ulang Sertifikat Elektronik',
                default => 'Hubungi petugas',
            },
            '{proses_aktual}' => $ticket->actual_process_label,
            '{hasil_aktual}' => $ticket->service_outcome
                ? $ticket->service_outcome_label
                : 'belum tercatat (layanan yang diajukan: ' . $ticket->service_label . ')',
            '{form_url}' => route('public.form'),
            '{nama_verifikator}' => (string) ($verifier?->name ?? 'Tim Verifikator TTE'),
            '{inisial_verifikator}' =>
                (string) ($verifier?->initials ?: $verifier?->name ?: 'Tim Verifikator TTE'),
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

    /** Desktop stays in WhatsApp Web. */
    public function url(Ticket $ticket, string $message): string {
        return 'https://web.whatsapp.com/send?phone=' .
            $ticket->no_wa .
            '&text=' .
            rawurlencode($message);
    }

    /** Official universal link: native WhatsApp when installed on iOS/Android. */
    public function mobileUrl(Ticket $ticket, string $message): string {
        return 'https://wa.me/' . $ticket->no_wa . '?text=' . rawurlencode($message);
    }

    /** Also works when JavaScript is unavailable (native form POST). */
    public function urlForRequest(Request $request, Ticket $ticket, string $message): string {
        $mobileHint = $request->input('wa_client') === 'mobile';
        $mobileUserAgent =
            preg_match(
                '/Android|iPhone|iPad|iPod|IEMobile|Windows Phone|Opera Mini/i',
                (string) $request->userAgent(),
            ) === 1;
        return $mobileHint || $mobileUserAgent
            ? $this->mobileUrl($ticket, $message)
            : $this->url($ticket, $message);
    }
}
