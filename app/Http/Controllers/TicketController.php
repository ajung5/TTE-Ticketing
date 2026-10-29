<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\WaNotification;
use App\Services\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller {
    private function verifier(Request $request): void {
        abort_unless($request->user()->isVerifier(), 403, 'Hanya verifikator yang dapat mengakses tiket.');
    }

    private function owner(Request $request, Ticket $ticket): void {
        $this->verifier($request);
        abort_unless($ticket->claimed_by === $request->user()->id, 403, 'Tiket bukan milik Anda.');
    }

    public function index(Request $request) {
        $this->verifier($request);
        // Tiket milik petugas lain tidak ditampilkan agar data pemohon tetap terbatas.
        $query = Ticket::query()
            ->with('claimant')
            ->where(fn($q) => $q->where('status', 'Open')->orWhere('claimed_by', $request->user()->id))
            ->latest();
        if (in_array($request->query('status'), ['Open', 'In Progress', 'Done'], true)) {
            $query->where('status', $request->query('status'));
        }
        if (in_array($request->query('layanan'), ['Reset Passphrase', 'Pembaruan', 'Pendaftaran'], true)) {
            $query->where('layanan', $request->query('layanan'));
        }
        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(
                fn($q) => $q
                    ->where('nama_user', 'like', '%' . $search . '%')
                    ->orWhere('ticket_number', 'like', '%' . $search . '%')
            );
        }
        $tickets = $query->paginate(12)->withQueryString();
        $stats = [
            'new' => Ticket::where('status', 'Open')->count(),
            'mine' => Ticket::where('claimed_by', $request->user()->id)->where('status', 'In Progress')->count(),
            'completed' => Ticket::where('claimed_by', $request->user()->id)->where('status', 'Done')->count()
        ];

        return view('tickets.index', compact('tickets', 'stats'));
    }

    /** Hanya hasil pemeriksaan manual AMS BSrE yang menentukan pesan layanan. */
    private function serviceEvent(Ticket $ticket): ?string {
        if (!$ticket->wa_checked_at || $ticket->certificate_status === 'unchecked') return null;
        if ($ticket->certificate_status === 'inactive') return 'unregistered';
        if ($ticket->certificate_status !== 'active') return null;
        if ($ticket->wa_verification_status === 'unverified') return 'unverified';
        if ($ticket->wa_verification_status !== 'verified') return null;

        return match ($ticket->layanan) {
            'Reset Passphrase' => 'reset',
            'Pembaruan' => 'renewal',
            'Pendaftaran' => 'registration',
            default => null,
        };
    }

    public function show(Request $request, Ticket $ticket, WhatsAppMessage $whatsapp) {
        $this->verifier($request);
        abort_unless($ticket->status === 'Open' || $ticket->claimed_by === $request->user()->id, 403);
        $ticket->load(['claimant', 'activities.actor', 'whatsappNotifications']);
        $serviceEvent = $ticket->status === 'In Progress' ? $this->serviceEvent($ticket) : null;
        $serviceMessage = $serviceEvent ? $whatsapp->forServiceTicket($ticket, $serviceEvent) : null;
        $serviceNotification = $serviceEvent ? $ticket->whatsappNotifications->firstWhere('event_type', $serviceEvent) : null;
        $serviceConfirmed = $serviceNotification?->confirmed_at !== null;
        $waMessage = $ticket->status === 'Done' ? $whatsapp->forCompletedTicket($ticket) : null;

        return view('tickets.show', compact('ticket', 'serviceEvent', 'serviceMessage', 'serviceNotification', 'serviceConfirmed', 'waMessage'));
    }

    public function signature(Request $request, Ticket $ticket) {
        // Signature evidence is private: only the verifier who claimed this ticket may view it.
        $this->owner($request, $ticket);
        abort_unless(
            is_string($ticket->signature_path) &&
                str_starts_with($ticket->signature_path, 'signatures/') &&
                \Illuminate\Support\Facades\Storage::disk('local')->exists($ticket->signature_path),
            404
        );

        return \Illuminate\Support\Facades\Storage::disk('local')->response($ticket->signature_path, null, [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => 'image/png'
        ]);
    }

    public function claim(Request $request, Ticket $ticket) {
        $this->verifier($request);
        DB::transaction(function () use ($ticket, $request) {
            // Atomic compare-and-set: tepat satu petugas dapat mengambil tiket ini.
            $updated = Ticket::whereKey($ticket->id)
                ->where('status', 'Open')
                ->whereNull('claimed_by')
                ->update([
                    'status' => 'In Progress',
                    'claimed_by' => $request->user()->id,
                    'claimed_at' => now()
                ]);
            abort_unless($updated === 1, 409, 'Tiket sudah diambil verifikator lain.');
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $request->user()->id,
                'action' => 'claimed'
            ]);
        });

        return redirect()->route('tickets.show', $ticket)->with('success', 'Tiket berhasil Anda ambil.');
    }

    public function release(Request $request, Ticket $ticket) {
        $this->owner($request, $ticket);
        DB::transaction(function () use ($ticket, $request) {
            $record = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->claimed_by === $request->user()->id && $record->status === 'In Progress', 409);
            abort_if(
                TicketActivity::where('ticket_id', $record->id)
                    ->whereNotIn('action', ['claimed', 'released'])
                    ->exists(),
                409,
                'Tiket sudah mempunyai catatan tindak lanjut dan tidak dapat dilepas.'
            );
            $record->update(['status' => 'Open', 'claimed_by' => null, 'claimed_at' => null]);
            TicketActivity::create([
                'ticket_id' => $record->id,
                'actor_id' => $request->user()->id,
                'action' => 'released'
            ]);
        });

        return redirect()->route('tickets.index')->with('success', 'Tiket dikembalikan ke antrean.');
    }

    /** Catat status sertifikat dan WA berdasarkan pemeriksaan manual pada AMS BSrE. */
    public function verifyWhatsApp(Request $request, Ticket $ticket) {
        $this->owner($request, $ticket);
        $data = $request->validate([
            'certificate_status' => ['required', 'in:active,inactive'],
            'wa_verification_status' => ['required', 'in:verified,unverified'],
            'wa_verification_note' => ['nullable', 'string', 'max:500'],
        ]);
        DB::transaction(function () use ($request, $ticket, $data) {
            $record = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->claimed_by === $request->user()->id && $record->status === 'In Progress', 409);
            $changed = $record->certificate_status !== $data['certificate_status'] ||
                $record->wa_verification_status !== $data['wa_verification_status'];
            $record->forceFill([
                'certificate_status' => $data['certificate_status'],
                'wa_verification_status' => $data['wa_verification_status'],
                'wa_verification_note' => $data['wa_verification_note'] ?? null,
                'wa_checked_at' => now(),
                'wa_checked_by' => $request->user()->id,
            ])->save();
            if ($changed) {
                // Bukti konfirmasi lama tidak boleh mengizinkan penyelesaian setelah status AMS berubah.
                WaNotification::where('ticket_id', $ticket->id)
                    ->whereIn('event_type', ['unverified','unregistered','reset','renewal','registration'])
                    ->update(['opened_at' => null, 'confirmed_at' => null]);
            }
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $request->user()->id,
                'action' => 'ams_checked',
                'note' => 'Sertifikat: '.($data['certificate_status'] === 'active' ? 'Aktif' : 'Belum aktif').
                    '; WA: '.($data['wa_verification_status'] === 'verified' ? 'Terverifikasi' : 'Belum terverifikasi').
                    (!empty($data['wa_verification_note']) ? '; '.$data['wa_verification_note'] : ''),
            ]);
        });
        return back()->with('success', 'Hasil pemeriksaan AMS BSrE disimpan. Notifikasi disesuaikan secara otomatis.');
    }

    public function openServiceWhatsApp(Request $request, Ticket $ticket, WhatsAppMessage $whatsapp) {
        $this->owner($request, $ticket);
        abort_if($ticket->source === 'simulated', 403, 'WhatsApp dinonaktifkan untuk tiket simulasi.');
        abort_unless($ticket->status === 'In Progress' && ($event = $this->serviceEvent($ticket)), 409,
            'Lengkapi pemeriksaan AMS sebelum mengirim notifikasi.');
        $ticket->load('claimant');
        $message = $whatsapp->forServiceTicket($ticket, $event);
        WaNotification::updateOrCreate(
            ['ticket_id' => $ticket->id, 'event_type' => $event],
            ['verifier_id' => $request->user()->id, 'message' => $message, 'opened_at' => now()]
        );
        return redirect()->away($whatsapp->url($ticket, $message));
    }

    public function confirmServiceWhatsApp(Request $request, Ticket $ticket) {
        $this->owner($request, $ticket);
        abort_if($ticket->source === 'simulated', 403);
        abort_unless($ticket->status === 'In Progress' && ($event = $this->serviceEvent($ticket)), 409);
        DB::transaction(function () use ($ticket, $request, $event) {
            $notification = WaNotification::where('ticket_id', $ticket->id)
                ->where('event_type', $event)->lockForUpdate()->first();
            abort_unless($notification && $notification->opened_at, 409, 'Buka WhatsApp terlebih dahulu.');
            if (!$notification->confirmed_at) {
                $notification->update(['confirmed_at' => now()]);
                TicketActivity::create([
                    'ticket_id' => $ticket->id,
                    'actor_id' => $request->user()->id,
                    'action' => 'wa_service_confirmed',
                    'note' => \App\Models\WaMessageTemplate::EVENTS[$event],
                ]);
            }
        });
        return back()->with('success', 'Pengiriman notifikasi dicatat secara manual.');
    }

    public function note(Request $request, Ticket $ticket) {
        $this->owner($request, $ticket);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:3000']]);
        $note = trim((string) ($data['note'] ?? ''));
        if ($note === '') return back()->with('success', 'Tidak ada catatan baru.');
        DB::transaction(function () use ($ticket, $request, $note) {
            $record = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->claimed_by === $request->user()->id && $record->status === 'In Progress', 409);
            TicketActivity::create([
                'ticket_id' => $record->id,
                'actor_id' => $request->user()->id,
                'action' => 'note', 'note' => $note,
            ]);
        });
        return back()->with('success', 'Catatan tindak lanjut tersimpan.');
    }

    public function complete(Request $request, Ticket $ticket) {
        $this->owner($request, $ticket);
        DB::transaction(function () use ($ticket, $request) {
            $record = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->claimed_by === $request->user()->id && $record->status === 'In Progress', 409);
            $event = $this->serviceEvent($record);
            abort_unless($event, 409, 'Hasil pemeriksaan AMS BSrE belum lengkap.');
            if ($record->source !== 'simulated') {
                abort_unless(WaNotification::where('ticket_id', $record->id)
                    ->where('event_type', $event)->whereNotNull('confirmed_at')->exists(), 409,
                    'Kirim dan konfirmasi notifikasi layanan terlebih dahulu.');
            }
            $record->update(['status' => 'Done', 'completed_at' => now(), 'resolution_note' => null]);
            TicketActivity::create([
                'ticket_id' => $record->id,
                'actor_id' => $request->user()->id, 'action' => 'completed',
            ]);
        });
        return back()->with('success', 'Tiket selesai. Notifikasi Selesai + Survei sekarang tersedia.');
    }

    public function openWhatsApp(Request $request, Ticket $ticket, WhatsAppMessage $whatsapp) {
        $this->owner($request, $ticket);
        abort_if($ticket->source === 'simulated', 403, 'WhatsApp dinonaktifkan untuk tiket simulasi.');
        abort_unless($ticket->status === 'Done', 409);
        $ticket->load('claimant');
        $message = $whatsapp->forCompletedTicket($ticket);
        WaNotification::updateOrCreate(
            ['ticket_id' => $ticket->id, 'event_type' => 'completed'],
            ['verifier_id' => $request->user()->id, 'message' => $message, 'opened_at' => now()]
        );
        return redirect()->away($whatsapp->url($ticket, $message));
    }

    public function confirmWhatsApp(Request $request, Ticket $ticket) {
        $this->owner($request, $ticket);
        abort_if($ticket->source === 'simulated', 403);
        abort_unless($ticket->status === 'Done', 409);
        $notification = WaNotification::where('ticket_id', $ticket->id)->where('event_type', 'completed')->first();
        abort_unless($notification && $notification->opened_at, 409, 'Buka pesan WhatsApp terlebih dahulu.');
        if (!$notification->confirmed_at) {
            $notification->update(['confirmed_at' => now()]);
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $request->user()->id,
                'action' => 'wa_confirmed',
            ]);
        }
        return back()->with('success', 'Notifikasi Selesai + Survei telah dikonfirmasi manual.');
    }
}
