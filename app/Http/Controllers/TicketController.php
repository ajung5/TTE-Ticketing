<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\WaNotification;
use App\Services\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

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
        if (in_array($request->query('status'), ['Open', 'In Progress', 'Done', 'Returned'], true)) {
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
            'completed' => Ticket::where('claimed_by', $request->user()->id)->where('status', 'Done')->count(),
            'returned' => Ticket::where('claimed_by', $request->user()->id)->where('status', 'Returned')->count()
        ];

        return view('tickets.index', compact('tickets', 'stats'));
    }

    /** Hanya hasil pemeriksaan manual AMS BSrE yang menentukan pesan layanan. */
    private function serviceEvent(Ticket $ticket): ?string {
        if (!$ticket->wa_checked_at) {
            return null;
        }
        // Verifikasi WA bukan bukti sertifikat telah terbit.
        // Pengaturan passphrase pada email BSrE bisa masih tertunda.
        if (
            $ticket->certificate_issuance_status === 'not_issued' &&
            ($ticket->wa_verification_status === 'verified' || $ticket->activation_status === 'activated')
        ) {
            return 'certificate_not_issued';
        }
        // Sesudah SE Belum Terbit dikonfirmasi dan hasil AMS menyatakan SE terbit,
        // lewati pesan layanan antara. Tampilkan Selesai + Survei setelah tiket ditutup.
        if ($this->readyAfterCertificateIssuance($ticket)) {
            return 'ready_for_completion'; // State internal, BUKAN event WhatsApp.
        }
        if ($ticket->wa_verification_status === 'verified') {
            if ($ticket->certificate_issuance_status !== 'issued') {
                return null;
            }
            if ($ticket->layanan === 'Pendaftaran') {
                return $this->hasOnboardingHistory($ticket) ? 'registration' : 'service_mismatch';
            }
            return match ($ticket->layanan) {
                'Reset Passphrase' => 'reset',
                'Pembaruan' => 'renewal',
                default => null
            };
        }
        if ($ticket->wa_verification_status !== 'unverified') {
            return null;
        }
        if ($ticket->activation_status === 'not_activated') {
            return $ticket->layanan === 'Pendaftaran' ? 'unregistered' : 'service_mismatch';
        }
        if ($ticket->activation_status !== 'activated') {
            return null;
        }
        if ($ticket->certificate_issuance_status === 'not_issued') {
            return 'certificate_not_issued';
        }
        if ($ticket->certificate_issuance_status === 'issued') {
            if ($ticket->layanan === 'Pendaftaran' && !$this->hasOnboardingHistory($ticket)) {
                return 'service_mismatch';
            }
            return 'unverified';
        }
        return null;
    }

    /** Konfirmasi SE Belum Terbit sebelumnya harus tercatat dalam audit trail. */
    private function readyAfterCertificateIssuance(Ticket $ticket): bool {
        if (!$ticket->wa_checked_at ||
            $ticket->wa_verification_status !== 'verified' ||
            $ticket->certificate_issuance_status !== 'issued') {
            return false;
        }
        return TicketActivity::query()
            ->where('ticket_id', $ticket->id)
            ->where('action', 'wa_service_confirmed')
            ->where('note', \App\Models\WaMessageTemplate::EVENTS['certificate_not_issued'])
            ->exists();
    }

    private function hasOnboardingHistory(Ticket $ticket): bool {
        return TicketActivity::query()
            ->where('ticket_id', $ticket->id)
            ->where('action', 'wa_service_confirmed')
            ->whereIn('note', [
                \App\Models\WaMessageTemplate::EVENTS['unregistered'],
                \App\Models\WaMessageTemplate::EVENTS['certificate_not_issued']
            ])
            ->exists();
    }

    public function show(Request $request, Ticket $ticket, WhatsAppMessage $whatsapp) {
        $this->verifier($request);
        abort_unless($ticket->status === 'Open' || $ticket->claimed_by === $request->user()->id, 403);
        $ticket->load(['claimant', 'activities.actor', 'whatsappNotifications']);
        $serviceEvent = $ticket->status === 'In Progress' ? $this->serviceEvent($ticket) : null;
        // ready_for_completion tidak mempunyai preset WhatsApp; pesan akhir baru
        // muncul ketika tiket benar-benar Done.
        $serviceMessage = $serviceEvent && $serviceEvent !== 'ready_for_completion'
            ? $whatsapp->forServiceTicket($ticket, $serviceEvent)
            : null;
        $serviceNotification = $serviceEvent
            ? $ticket->whatsappNotifications->firstWhere('event_type', $serviceEvent)
            : null;
        $serviceConfirmed = $serviceNotification?->confirmed_at !== null;
        $waMessage = $ticket->status === 'Done' ? $whatsapp->forCompletedTicket($ticket) : null;

        return view(
            'tickets.show',
            compact('ticket', 'serviceEvent', 'serviceMessage', 'serviceNotification', 'serviceConfirmed', 'waMessage')
        );
    }

    public function signature(Request $request, Ticket $ticket) {
        $this->owner($request, $ticket);

        $path = $ticket->signature_path;

        abort_unless(is_string($path) && str_starts_with($path, 'signatures/'), 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
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
            'wa_verification_status' => ['required', Rule::in(['verified', 'unverified'])],
            'activation_status' => ['nullable', Rule::in(['activated', 'not_activated'])],
            'certificate_issuance_status' => ['nullable', Rule::in(['issued', 'not_issued'])],
            'recommended_service' => ['nullable', Rule::in(['Reset Passphrase', 'Pembaruan'])],
            'wa_verification_note' => ['nullable', 'string', 'max:500'],
            // Kompatibilitas request pengujian/klien internal versi sebelumnya.
            'certificate_status' => ['nullable', Rule::in(['active', 'inactive'])]
        ]);

        $wa = $data['wa_verification_status'];
        $activation = null;
        $issuance = null;
        if ($wa === 'verified') {
            // Status penerbitan SE harus diperiksa secara eksplisit, bahkan ketika WA sudah verified.
            $issuance = $data['certificate_issuance_status'] ?? null;
            if ($issuance === null) {
                throw ValidationException::withMessages([
                    'certificate_issuance_status' =>
                        'Periksa status penerbitan SE meskipun nomor WA sudah terverifikasi.'
                ]);
            }
        } elseif ($wa === 'unverified') {
            $activation = $data['activation_status'] ?? null;
            $issuance = $data['certificate_issuance_status'] ?? null;
            if ($activation === null && isset($data['certificate_status'])) {
                $activation = $data['certificate_status'] === 'active' ? 'activated' : 'not_activated';
                $issuance = $activation === 'activated' ? 'issued' : null;
            }
            if ($activation === null) {
                throw ValidationException::withMessages([
                    'activation_status' => 'Status aktivasi akun wajib dipilih jika WA belum terverifikasi.'
                ]);
            }
            if ($activation === 'activated' && $issuance === null) {
                throw ValidationException::withMessages([
                    'certificate_issuance_status' => 'Status penerbitan SE wajib dipilih jika akun sudah aktivasi.'
                ]);
            }
            if ($activation === 'not_activated') {
                $issuance = null;
            }
        }

        DB::transaction(function () use ($request, $ticket, $data, $wa, $activation, $issuance) {
            $record = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->claimed_by === $request->user()->id && $record->status === 'In Progress', 409);
            $previousEvent = $this->serviceEvent($record);
            $mismatch = $record->layanan !== 'Pendaftaran' && $wa === 'unverified' && $activation === 'not_activated';
            $mismatch =
                $mismatch ||
                ($record->layanan === 'Pendaftaran' &&
                    ($issuance === 'issued' &&
                        ($wa === 'verified' || ($wa === 'unverified' && $activation === 'activated'))) &&
                    !$this->hasOnboardingHistory($record));
            $recommended = null;
            if ($mismatch) {
                if ($record->layanan === 'Pendaftaran') {
                    $recommended = $data['recommended_service'] ?? null;
                    if ($recommended === null) {
                        throw ValidationException::withMessages([
                            'recommended_service' =>
                                'Pilih layanan yang disarankan setelah klarifikasi kebutuhan pemohon.'
                        ]);
                    }
                } else {
                    $recommended = 'Pendaftaran';
                }
            }
            // Jangan tandai SE aktif hanya karena nomor WA sudah terverifikasi.
            $legacyCertificate = $issuance === 'issued' ? 'active' : 'inactive';
            $changed =
                $record->wa_verification_status !== $wa ||
                $record->activation_status !== $activation ||
                $record->certificate_issuance_status !== $issuance ||
                $record->recommended_service !== $recommended;
            $record
                ->forceFill([
                    'wa_verification_status' => $wa,
                    'activation_status' => $activation,
                    'certificate_issuance_status' => $issuance,
                    'certificate_status' => $legacyCertificate,
                    'recommended_service' => $recommended,
                    'wa_verification_note' => $data['wa_verification_note'] ?? null,
                    'wa_checked_at' => now(),
                    'wa_checked_by' => $request->user()->id
                ])
                ->save();
            if ($changed) {
                // Konfirmasi notifikasi lama tidak berlaku sesudah hasil pemeriksaan berubah.
                WaNotification::where('ticket_id', $record->id)
                    ->whereIn('event_type', array_keys(\App\Models\WaMessageTemplate::EVENTS))
                    ->update(['opened_at' => null, 'confirmed_at' => null]);
            }
            TicketActivity::create([
                'ticket_id' => $record->id,
                'actor_id' => $request->user()->id,
                'action' => 'ams_checked',
                'note' =>
                    'WA: ' .
                    ($wa === 'verified' ? 'Terverifikasi' : 'Belum terverifikasi') .
                    '; Aktivasi: ' .
                    ($activation ?? 'Dilewati (inferensi)') .
                    '; SE: ' .
                    ($issuance ?? 'Dilewati') .
                    '; Keputusan: ' .
                    ($this->serviceEvent($record) ?? 'Belum ada') .
                    ($recommended ? '; Disarankan: ' . $recommended : '') .
                    (!empty($data['wa_verification_note']) ? '; ' . $data['wa_verification_note'] : '')
            ]);
        });
        return back()->with(
            'success',
            'Pemeriksaan AMS berhasil disimpan. Jenis notifikasi disesuaikan dengan kondisi akun.'
        );
    }

    public function openServiceWhatsApp(Request $request, Ticket $ticket, WhatsAppMessage $whatsapp) {
        $this->owner($request, $ticket);
        abort_if($ticket->source === 'simulated', 403, 'WhatsApp dinonaktifkan untuk tiket simulasi.');
        $event = $this->serviceEvent($ticket);
        abort_unless(
            $ticket->status === 'In Progress' && $event !== null && $event !== 'ready_for_completion',
            409,
            'Lengkapi pemeriksaan AMS sebelum mengirim notifikasi.'
        );
        $ticket->load('claimant');
        $message = $whatsapp->forServiceTicket($ticket, $event);
        WaNotification::updateOrCreate(
            ['ticket_id' => $ticket->id, 'event_type' => $event],
            ['verifier_id' => $request->user()->id, 'message' => $message, 'opened_at' => now(), 'confirmed_at' => null]
        );
        $whatsappUrl = $whatsapp->url($ticket, $message);
        if ($request->expectsJson()) {
            return response()->json(['url' => $whatsappUrl]);
        }
        return redirect()->away($whatsappUrl);
    }

    public function confirmServiceWhatsApp(Request $request, Ticket $ticket) {
        $this->owner($request, $ticket);
        abort_if($ticket->source === 'simulated', 403);
        DB::transaction(function () use ($ticket, $request) {
            $record = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'In Progress' && $record->claimed_by === $request->user()->id, 409);
            $event = $this->serviceEvent($record);
            abort_unless($event !== null && $event !== 'ready_for_completion', 409);
            $notification = WaNotification::where('ticket_id', $record->id)
                ->where('event_type', $event)
                ->lockForUpdate()
                ->first();
            abort_unless($notification && $notification->opened_at, 409, 'Buka WhatsApp terlebih dahulu.');
            if (!$notification->confirmed_at) {
                $notification->update(['confirmed_at' => now()]);
                TicketActivity::create([
                    'ticket_id' => $record->id,
                    'actor_id' => $request->user()->id,
                    'action' => 'wa_service_confirmed',
                    'note' => \App\Models\WaMessageTemplate::EVENTS[$event]
                ]);
            }
        });
        return back()->with('success', 'Pengiriman notifikasi dikonfirmasi. Jika layanan tidak sesuai, lanjutkan dengan tombol Kembalikan Permohonan.');
    }

    /** Kembalikan salah jenis layanan setelah pesan koreksi dikonfirmasi secara manual. */
    public function returnForCorrection(Request $request, Ticket $ticket) {
        $this->owner($request, $ticket);
        abort_if($ticket->source === 'simulated', 403, 'Tiket simulasi tidak dapat dikembalikan melalui alur WhatsApp.');
        DB::transaction(function () use ($request, $ticket) {
            $record = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $record->status === 'In Progress' && $record->claimed_by === $request->user()->id,
                409,
                'Hanya tiket yang sedang Anda tangani dapat dikembalikan.'
            );
            abort_unless(
                $this->serviceEvent($record) === 'service_mismatch' &&
                in_array($record->recommended_service, ['Pendaftaran', 'Reset Passphrase', 'Pembaruan'], true),
                409,
                'Kembalikan permohonan hanya jika pemeriksaan AMS menyatakan jenis layanan tidak sesuai.'
            );
            abort_unless(
                WaNotification::where('ticket_id', $record->id)
                    ->where('event_type', 'service_mismatch')
                    ->whereNotNull('confirmed_at')
                    ->exists(),
                409,
                'Kirim dan konfirmasi pesan Perbaikan Jenis Permohonan terlebih dahulu.'
            );
            $record->update([
                'status' => 'Returned',
                'completed_at' => null,
                'resolution_note' => 'Pengajuan ulang disarankan: ' . $record->recommended_service
            ]);
            TicketActivity::create([
                'ticket_id' => $record->id,
                'actor_id' => $request->user()->id,
                'action' => 'returned_for_correction',
                'note' => 'Layanan diajukan: ' . $record->layanan .
                    '; disarankan: ' . $record->recommended_service
            ]);
        });
        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Permohonan berhasil dikembalikan. Tiket lama disimpan sebagai Perlu Pengajuan Ulang.');
    }

    public function note(Request $request, Ticket $ticket) {
        $this->owner($request, $ticket);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:3000']]);
        $note = trim((string) ($data['note'] ?? ''));
        if ($note === '') {
            return back()->with('success', 'Tidak ada catatan baru.');
        }
        DB::transaction(function () use ($ticket, $request, $note) {
            $record = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->claimed_by === $request->user()->id && $record->status === 'In Progress', 409);
            TicketActivity::create([
                'ticket_id' => $record->id,
                'actor_id' => $request->user()->id,
                'action' => 'note',
                'note' => $note
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
            abort_unless(
                in_array($event, ['reset', 'renewal', 'registration', 'ready_for_completion'], true),
                409,
                'Tiket belum dapat diselesaikan. Tindak lanjuti hasil AMS atau pengajuan ulang terlebih dahulu.'
            );
            // Gate utama: hanya reset/renewal/registration; konfirmasi WA bersifat administratif.
            $serviceNotificationConfirmed =
                $event === 'ready_for_completion' ||
                $record->source === 'simulated' ||
                WaNotification::where('ticket_id', $record->id)
                    ->where('event_type', $event)
                    ->whereNotNull('confirmed_at')
                    ->exists();
            $record->update(['status' => 'Done', 'completed_at' => now(), 'resolution_note' => null]);
            TicketActivity::create([
                'ticket_id' => $record->id,
                'actor_id' => $request->user()->id,
                'action' => 'completed',
                'note' => $event === 'ready_for_completion'
                    ? 'SE telah terbit setelah notifikasi SE Belum Terbit dikonfirmasi; notifikasi Selesai + Survei tersedia.'
                    : ($serviceNotificationConfirmed
                        ? null
                        : 'Konfirmasi pengiriman notifikasi layanan belum tercatat pada saat tiket diselesaikan.')
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
        $whatsappUrl = $whatsapp->url($ticket, $message);
        if ($request->expectsJson()) {
            return response()->json(['url' => $whatsappUrl]);
        }
        return redirect()->away($whatsappUrl);
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
                'action' => 'wa_confirmed'
            ]);
        }
        return back()->with('success', 'Notifikasi Selesai + Survei telah dikonfirmasi manual.');
    }
}
