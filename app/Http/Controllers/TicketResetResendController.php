<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketResetResend;
use App\Services\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Manual AMS resend evidence, not an API integration with BSrE. */
class TicketResetResendController extends Controller {
    private function owner(Request $request, Ticket $ticket): void {
        abort_unless($request->user()?->isVerifier(), 403);
        abort_unless($ticket->claimed_by === $request->user()->id, 403, 'Tiket bukan milik Anda.');
        abort_unless($ticket->layanan === 'Reset Passphrase', 409);
        abort_unless(in_array($ticket->status, ['In Progress', 'Done'], true), 409);
        abort_if($ticket->source === 'simulated', 403);
    }

    private function attempt(Request $request, Ticket $ticket, TicketResetResend $resend): void {
        $this->owner($request, $ticket);
        abort_unless($resend->ticket_id === $ticket->id, 404);
        abort_unless($resend->verifier_id === $request->user()->id,
            403, 'Riwayat pengiriman ini milik petugas sebelumnya. Buat permintaan baru.');
    }

    public function store(Request $request, Ticket $ticket, WhatsAppMessage $whatsapp) {
        $this->owner($request, $ticket);
        $data = $request->validate([
            'reason' => ['required', Rule::in(['not_received', 'expired', 'cannot_open'])],
            'ams_confirmed' => ['required', 'accepted'],
        ]);
        DB::transaction(function () use ($ticket, $request, $data, $whatsapp) {
            $record = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->owner($request, $record);
            if (!filter_var($record->email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'reason' => 'Email dinas pemohon belum tersedia atau tidak valid.',
                ]);
            }
            $last = TicketResetResend::where('ticket_id', $record->id)->latest('id')->first();
            if ($last && $last->created_at->greaterThan(now()->subMinutes(2))) {
                throw ValidationException::withMessages([
                    'reason' => 'Kirim ulang berikutnya baru dapat dicatat setelah 2 menit.',
                ]);
            }
            if (TicketResetResend::where('ticket_id', $record->id)
                ->where('created_at', '>=', now()->subDay())->count() >= 5) {
                throw ValidationException::withMessages([
                    'reason' => 'Batas 5 permintaan per 24 jam tercapai. Eskalasi jika masih gagal.',
                ]);
            }
            $record->load('claimant');
            $message = $whatsapp->forServiceTicket($record, 'reset_resend');
            $attempt = TicketResetResend::create([
                'ticket_id' => $record->id,
                'verifier_id' => $request->user()->id,
                'reason' => $data['reason'],
                'message' => $message,
            ]);
            $reason = match ($data['reason']) {
                'expired' => 'Tautan kedaluwarsa',
                'cannot_open' => 'Tautan tidak dapat digunakan',
                default => 'Email belum diterima',
            };
            TicketActivity::create([
                'ticket_id' => $record->id,
                'actor_id' => $request->user()->id,
                'action' => 'reset_link_resent',
                'note' => 'Permintaan #' . $attempt->id . '; alasan: ' . $reason .
                    '; verifikator menyatakan telah mengirim ulang melalui AMS BSrE. ' .
                    'Belum ada konfirmasi email diterima.',
            ]);
        });
        return redirect()->route('tickets.show', $ticket)->with('success',
            'Kirim ulang melalui AMS tercatat. Silakan buka WhatsApp dan konfirmasi setelah pesan dikirim.');
    }

    public function openWhatsApp(
        Request $request, Ticket $ticket, TicketResetResend $resend, WhatsAppMessage $whatsapp,
    ) {
        $this->attempt($request, $ticket, $resend);
        DB::transaction(function () use ($request, $ticket, $resend) {
            $record = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->owner($request, $record);
            $attempt = TicketResetResend::whereKey($resend->id)->lockForUpdate()->firstOrFail();
            $this->attempt($request, $record, $attempt);
            abort_if($attempt->wa_confirmed_at !== null, 409, 'Pesan ini sudah dikonfirmasi.');
            $attempt->forceFill(['wa_opened_at' => now()])->save();
        });
        $url = $whatsapp->urlForRequest($request, $ticket, $resend->message);
        if ($request->expectsJson()) {
            return response()->json(['url' => $url]);
        }
        return redirect()->away($url);
    }

    public function confirmWhatsApp(Request $request, Ticket $ticket, TicketResetResend $resend) {
        $this->attempt($request, $ticket, $resend);
        DB::transaction(function () use ($ticket, $resend, $request) {
            $record = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->owner($request, $record);
            $attempt = TicketResetResend::whereKey($resend->id)->lockForUpdate()->firstOrFail();
            $this->attempt($request, $record, $attempt);
            abort_unless($attempt->wa_opened_at !== null, 409, 'Buka WhatsApp lebih dahulu.');
            abort_if($attempt->wa_confirmed_at !== null, 409, 'Pesan sudah dikonfirmasi.');
            $attempt->forceFill(['wa_confirmed_at' => now()])->save();
            TicketActivity::create([
                'ticket_id' => $record->id,
                'actor_id' => $request->user()->id,
                'action' => 'reset_resend_wa_confirmed',
                'note' => 'Notifikasi pengiriman ulang #' . $attempt->id .
                    ' dikonfirmasi manual oleh verifikator; bukan bukti email BSrE diterima.',
            ]);
        });
        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Notifikasi WhatsApp kirim ulang dikonfirmasi manual.');
    }
}
