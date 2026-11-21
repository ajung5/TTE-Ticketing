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

/** Manual AMS link delivery evidence, not an API integration with BSrE. */
class TicketResetResendController extends Controller {
    private function owner(Request $request, Ticket $ticket): void {
        abort_unless($request->user()?->isVerifier(), 403);
        abort_unless($ticket->claimed_by === $request->user()->id, 403, 'Tiket bukan milik Anda.');
        if ($request->routeIs('tickets.reset-resends.*')) {
            abort_unless($ticket->layanan === 'Reset Passphrase', 409);
        }
        abort_unless(in_array($ticket->status, ['In Progress', 'Done'], true), 409);
        abort_if($ticket->source === 'simulated', 403);
    }

    private function attempt(Request $request, Ticket $ticket, TicketResetResend $resend): void {
        $this->owner($request, $ticket);
        abort_unless($resend->ticket_id === $ticket->id, 404);
        if ($request->routeIs('tickets.reset-resends.*')) {
            abort_unless($resend->link_type === 'reset_link', 404);
        }
        abort_unless(
            $resend->verifier_id === $request->user()->id,
            403,
            'Riwayat pengiriman ini milik petugas sebelumnya. Buat permintaan baru.',
        );
    }

    public function store(Request $request, Ticket $ticket, WhatsAppMessage $whatsapp) {
        $this->owner($request, $ticket);
        $legacy = $request->routeIs('tickets.reset-resends.store');
        if ($legacy) {
            $request->mergeIfMissing(['link_type' => 'reset_link']);
        }
        $data = $request->validate([
            'link_type' => [
                'required',
                Rule::in($legacy ? ['reset_link'] : array_keys(TicketResetResend::LINK_TYPES)),
            ],
            'reason' => [
                'required',
                Rule::in(
                    $legacy
                        ? ['not_received', 'expired', 'cannot_open']
                        : array_keys(TicketResetResend::REASONS),
                ),
            ],
            'ams_confirmed' => ['required', 'accepted'],
        ]);
        DB::transaction(function () use ($ticket, $request, $data, $whatsapp) {
            $record = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->owner($request, $record);
            $type = $data['link_type'];
            if ($type !== 'wa_verify_link' && !filter_var($record->email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'reason' => 'Email dinas pemohon belum tersedia atau tidak valid.',
                ]);
            }
            if (
                $type === 'wa_verify_link' &&
                !preg_match('/^628[0-9]{8,12}$/D', (string) $record->no_wa)
            ) {
                throw ValidationException::withMessages([
                    'link_type' => 'Periksa nomor WhatsApp pada tiket terlebih dahulu.',
                ]);
            }
            $history = $record->resetResends();
            if (
                $data['reason'] === 'initial' &&
                (clone $history)->where('link_type', $type)->exists()
            ) {
                throw ValidationException::withMessages([
                    'reason' =>
                        'Pengiriman tautan ini sudah pernah dicatat. Pilih alasan kirim ulang.',
                ]);
            }
            $last = (clone $history)->latest('id')->first();
            if ($last && $last->created_at->greaterThan(now()->subMinutes(2))) {
                throw ValidationException::withMessages([
                    'reason' => 'Pengiriman berikutnya dapat dicatat setelah 2 menit.',
                ]);
            }
            if ($history->where('created_at', '>=', now()->subDay())->count() >= 5) {
                throw ValidationException::withMessages([
                    'reason' =>
                        'Batas 5 permintaan per 24 jam tercapai. Eskalasi jika masih gagal.',
                ]);
            }
            $record->load('claimant');
            $resetResend = $type === 'reset_link' && $data['reason'] !== 'initial';
            $event = $resetResend ? 'reset_resend' : $type;
            $message = $whatsapp->forServiceTicket($record, $event);
            $attempt = TicketResetResend::create([
                'ticket_id' => $record->id,
                'verifier_id' => $request->user()->id,
                'link_type' => $type,
                'reason' => $data['reason'],
                'message' => $message,
            ]);
            TicketActivity::create([
                'ticket_id' => $record->id,
                'actor_id' => $request->user()->id,
                'action' => $resetResend ? 'reset_link_resent' : 'ams_link_sent',
                'note' =>
                    '#' .
                    $attempt->id .
                    ' · ' .
                    TicketResetResend::LINK_TYPES[$type] .
                    ' · ' .
                    TicketResetResend::REASONS[$data['reason']] .
                    '. Pengiriman melalui AMS BSrE dicatat oleh verifikator.',
            ]);
        });
        return redirect()
            ->route('tickets.show', $ticket)
            ->with(
                'success',
                'Pengiriman tautan melalui AMS tercatat. Silakan buka WhatsApp dan konfirmasi setelah pesan dikirim.',
            );
    }

    public function openWhatsApp(
        Request $request,
        Ticket $ticket,
        TicketResetResend $resend,
        WhatsAppMessage $whatsapp,
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
                'action' =>
                    $attempt->link_type === 'reset_link' && $attempt->reason !== 'initial'
                        ? 'reset_resend_wa_confirmed'
                        : 'ams_link_wa_confirmed',
                'note' =>
                    'Notifikasi ' .
                    TicketResetResend::LINK_TYPES[$attempt->link_type] .
                    ' #' .
                    $attempt->id .
                    ' dikonfirmasi manual oleh verifikator.',
            ]);
        });
        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Pengiriman pemberitahuan WhatsApp sudah dikonfirmasi.');
    }
}
