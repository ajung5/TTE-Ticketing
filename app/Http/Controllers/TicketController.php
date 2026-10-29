<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\WaNotification;
use App\Services\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    private function verifier(Request $request): void
    {
        abort_unless($request->user()->isVerifier(), 403, 'Hanya verifikator yang dapat mengakses tiket.');
    }

    private function owner(Request $request, Ticket $ticket): void
    {
        $this->verifier($request);
        abort_unless($ticket->claimed_by === $request->user()->id, 403, 'Tiket bukan milik Anda.');
    }

    public function index(Request $request)
    {
        $this->verifier($request);
        // Tiket milik petugas lain tidak ditampilkan agar data pemohon tetap terbatas.
        $query = Ticket::query()->with('claimant')
            ->where(fn ($q) => $q->where('status', 'Open')->orWhere('claimed_by', $request->user()->id))
            ->latest();
        if (in_array($request->query('status'), ['Open', 'In Progress', 'Done'], true)) {
            $query->where('status', $request->query('status'));
        }
        if (in_array($request->query('layanan'), ['Reset Passphrase', 'Pembaruan', 'Pendaftaran'], true)) {
            $query->where('layanan', $request->query('layanan'));
        }
        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(fn ($q) => $q->where('nama_user', 'like', '%'.$search.'%')
                ->orWhere('ticket_number', 'like', '%'.$search.'%'));
        }
        $tickets = $query->paginate(12)->withQueryString();
        $stats = [
            'new' => Ticket::where('status', 'Open')->count(),
            'mine' => Ticket::where('claimed_by', $request->user()->id)->where('status', 'In Progress')->count(),
            'completed' => Ticket::where('claimed_by', $request->user()->id)->where('status', 'Done')->count(),
        ];

        return view('tickets.index', compact('tickets', 'stats'));
    }

    public function show(Request $request, Ticket $ticket, WhatsAppMessage $whatsapp)
    {
        $this->verifier($request);
        abort_unless($ticket->status === 'Open' || $ticket->claimed_by === $request->user()->id, 403);
        $ticket->load(['claimant', 'activities.actor', 'whatsappNotifications']);
        $waMessage = $ticket->status === 'Done' && $ticket->wa_verification_status === 'verified'
            ? $whatsapp->forCompletedTicket($ticket) : null;
        $unverifiedMessage = $ticket->status === 'In Progress' && $ticket->wa_verification_status === 'unverified'
            ? $whatsapp->forUnverifiedTicket($ticket) : null;
        $followupMessage = $ticket->status === 'In Progress' && $ticket->wa_verification_status === 'verified'
            && $ticket->activities->contains('action', 'note')
            ? $whatsapp->forFollowUpTicket($ticket) : null;

        return view('tickets.show', compact('ticket', 'waMessage', 'followupMessage', 'unverifiedMessage'));
    }

    public function claim(Request $request, Ticket $ticket)
    {
        $this->verifier($request);
        DB::transaction(function () use ($ticket, $request) {
            // Atomic compare-and-set: tepat satu petugas dapat mengambil tiket ini.
            $updated = Ticket::whereKey($ticket->id)->where('status', 'Open')
                ->whereNull('claimed_by')->update([
                    'status' => 'In Progress', 'claimed_by' => $request->user()->id,
                    'claimed_at' => now(),
                ]);
            abort_unless($updated === 1, 409, 'Tiket sudah diambil verifikator lain.');
            TicketActivity::create(['ticket_id' => $ticket->id, 'actor_id' => $request->user()->id, 'action' => 'claimed']);
        });

        return redirect()->route('tickets.show', $ticket)->with('success', 'Tiket berhasil Anda ambil.');
    }

    public function release(Request $request, Ticket $ticket)
    {
        $this->owner($request, $ticket);
        DB::transaction(function () use ($ticket, $request) {
            $record = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->claimed_by === $request->user()->id && $record->status === 'In Progress', 409);
            abort_if(TicketActivity::where('ticket_id', $record->id)->whereNotIn('action', ['claimed', 'released'])->exists(),
                409, 'Tiket sudah mempunyai catatan tindak lanjut dan tidak dapat dilepas.');
            $record->update(['status' => 'Open', 'claimed_by' => null, 'claimed_at' => null]);
            TicketActivity::create(['ticket_id' => $record->id, 'actor_id' => $request->user()->id, 'action' => 'released']);
        });

        return redirect()->route('tickets.index')->with('success', 'Tiket dikembalikan ke antrean.');
    }

    /**
     * An authorised verifier manually records what they found in AMS BSrE.
     * Never infer registration from a valid phone format or a WhatsApp response.
     */
    public function verifyWhatsApp(Request $request, Ticket $ticket)
    {
        $this->owner($request, $ticket);
        $data = $request->validate([
            'wa_verification_status' => ['required', 'in:verified,unverified'],
            'wa_verification_note' => ['required', 'string', 'min:8', 'max:500'],
        ]);
        DB::transaction(function () use ($request, $ticket, $data) {
            $record = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->claimed_by === $request->user()->id && in_array($record->status, ['In Progress', 'Done'], true), 409);
            abort_if($record->status === 'Done' && $data['wa_verification_status'] !== 'verified', 409, 'Tiket selesai hanya dapat dilengkapi dengan verifikasi nomor.');
            $record->update([
                'wa_verification_status' => $data['wa_verification_status'],
                'wa_verification_note' => $data['wa_verification_note'],
                'wa_checked_at' => now(),
                'wa_checked_by' => $request->user()->id,
            ]);
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $request->user()->id,
                'action' => $data['wa_verification_status'] === 'verified' ? 'wa_verified' : 'wa_unverified',
                'note' => $data['wa_verification_note'],
            ]);
        });
        return back()->with('success', 'Hasil pemeriksaan nomor WhatsApp pada AMS BSrE berhasil dicatat.');
    }

    public function openUnverifiedWhatsApp(Request $request, Ticket $ticket, WhatsAppMessage $whatsapp)
    {
        $this->owner($request, $ticket);
        abort_if($ticket->source === 'simulated', 403, 'WhatsApp dinonaktifkan untuk tiket simulasi.');
        abort_unless($ticket->status === 'In Progress' && $ticket->wa_verification_status === 'unverified', 409);
        $ticket->load('claimant');
        $message = $whatsapp->forUnverifiedTicket($ticket);
        WaNotification::updateOrCreate(
            ['ticket_id' => $ticket->id, 'event_type' => 'unverified'],
            ['verifier_id' => $request->user()->id, 'message' => $message, 'opened_at' => now(), 'confirmed_at' => null]
        );
        return redirect()->away($whatsapp->url($ticket, $message));
    }

    public function confirmUnverifiedWhatsApp(Request $request, Ticket $ticket)
    {
        $this->owner($request, $ticket);
        abort_unless($ticket->status === 'In Progress' && $ticket->wa_verification_status === 'unverified', 409);
        $notification = WaNotification::where('ticket_id', $ticket->id)->where('event_type', 'unverified')->first();
        abort_unless($notification && $notification->opened_at, 409, 'Buka WhatsApp terlebih dahulu.');
        if (! $notification->confirmed_at) {
            $notification->update(['confirmed_at' => now()]);
            TicketActivity::create(['ticket_id' => $ticket->id, 'actor_id' => $request->user()->id, 'action' => 'wa_unverified_confirmed']);
        }
        return back()->with('success', 'Konfirmasi manual pesan nomor WA belum terverifikasi tersimpan.');
    }

    public function note(Request $request, Ticket $ticket)
    {
        $this->owner($request, $ticket);
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:3000']]);
        DB::transaction(function () use ($ticket, $request, $data) {
            $record = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->claimed_by === $request->user()->id && $record->status === 'In Progress', 409);
            TicketActivity::create(['ticket_id' => $record->id, 'actor_id' => $request->user()->id, 'action' => 'note', 'note' => $data['note']]);
        });

        return back()->with('success', 'Catatan tindak lanjut tersimpan.');
    }

    public function complete(Request $request, Ticket $ticket)
    {
        $this->owner($request, $ticket);
        $data = $request->validate(['resolution_note' => ['required', 'string', 'min:10', 'max:3000']]);
        DB::transaction(function () use ($ticket, $request, $data) {
            $record = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->claimed_by === $request->user()->id && $record->status === 'In Progress', 409);
            abort_unless($record->wa_verification_status === 'verified', 409, 'Nomor WhatsApp belum terverifikasi di AMS BSrE.');
            $record->update(['status' => 'Done', 'completed_at' => now(), 'resolution_note' => $data['resolution_note']]);
            TicketActivity::create(['ticket_id' => $record->id, 'actor_id' => $request->user()->id, 'action' => 'completed', 'note' => $data['resolution_note']]);
        });

        return back()->with('success', 'Tiket selesai. Anda dapat membuka dan mengonfirmasi pesan WhatsApp.');
    }

    public function openFollowUpWhatsApp(Request $request, Ticket $ticket, WhatsAppMessage $whatsapp)
    {
        $this->owner($request, $ticket);
        abort_if($ticket->source === 'simulated', 403, 'WhatsApp dinonaktifkan untuk tiket simulasi.');
        abort_unless($ticket->status === 'In Progress' && $ticket->wa_verification_status === 'verified', 409);
        abort_unless(TicketActivity::where('ticket_id', $ticket->id)->where('action', 'note')->exists(), 409, 'Catat tindak lanjut dahulu.');
        $ticket->load('claimant');
        $message = $whatsapp->forFollowUpTicket($ticket);
        WaNotification::updateOrCreate(
            ['ticket_id' => $ticket->id, 'event_type' => 'followup'],
            ['verifier_id' => $request->user()->id, 'message' => $message, 'opened_at' => now()]
        );

        return redirect()->away($whatsapp->url($ticket, $message));
    }

    public function confirmFollowUpWhatsApp(Request $request, Ticket $ticket)
    {
        $this->owner($request, $ticket);
        abort_unless($ticket->status === 'In Progress' && $ticket->wa_verification_status === 'verified', 409);
        $notification = WaNotification::where('ticket_id', $ticket->id)->where('event_type', 'followup')->first();
        abort_unless($notification && $notification->opened_at, 409, 'Buka pesan WhatsApp terlebih dahulu.');
        if (! $notification->confirmed_at) {
            $notification->update(['confirmed_at' => now()]);
            TicketActivity::create(['ticket_id' => $ticket->id, 'actor_id' => $request->user()->id, 'action' => 'wa_followup_confirmed']);
        }

        return back()->with('success', 'Konfirmasi tindak lanjut WhatsApp tercatat (manual).');
    }

    public function openWhatsApp(Request $request, Ticket $ticket, WhatsAppMessage $whatsapp)
    {
        $this->owner($request, $ticket);
        abort_if($ticket->source === 'simulated', 403, 'WhatsApp dinonaktifkan untuk tiket simulasi.');
        abort_unless($ticket->status === 'Done' && $ticket->wa_verification_status === 'verified', 409);
        $ticket->load('claimant');
        $message = $whatsapp->forCompletedTicket($ticket);
        WaNotification::updateOrCreate(
            ['ticket_id' => $ticket->id, 'event_type' => 'completed'],
            ['verifier_id' => $request->user()->id, 'message' => $message, 'opened_at' => now()]
        );

        return redirect()->away($whatsapp->url($ticket, $message));
    }

    public function confirmWhatsApp(Request $request, Ticket $ticket)
    {
        $this->owner($request, $ticket);
        abort_unless($ticket->status === 'Done' && $ticket->wa_verification_status === 'verified', 409);
        $notification = WaNotification::where('ticket_id', $ticket->id)->where('event_type', 'completed')->first();
        abort_unless($notification && $notification->opened_at, 409, 'Buka pesan WhatsApp terlebih dahulu.');
        if (! $notification->confirmed_at) {
            $notification->update(['confirmed_at' => now()]);
            TicketActivity::create(['ticket_id' => $ticket->id, 'actor_id' => $request->user()->id, 'action' => 'wa_confirmed']);
        }

        return back()->with('success', 'Konfirmasi manual tercatat. Ini bukan bukti delivery WhatsApp.');
    }
}
