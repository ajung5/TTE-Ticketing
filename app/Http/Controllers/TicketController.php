<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\WaNotification;
use App\Models\WaMessageTemplate;
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
        if ($search = trim((string) ($request->query('q') ?? ''))) {
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

    /** Pilih notifikasi dari hasil AMS aktual, bukan hanya layanan awal. */
    private function serviceEvent(Ticket $ticket): ?string {
        if (!$ticket->wa_checked_at || !in_array($ticket->activation_status, ['activated', 'not_activated'], true)) {
            return null;
        }
        if ($ticket->activation_status === 'not_activated') {
            // Penerbitan SE tetap berada pada alur Pendaftaran; aktivasi adalah tahap awal.
            return $ticket->layanan === 'Pendaftaran' ? 'registration' : 'activation_required';
        }
        if (!in_array($ticket->wa_verification_status, ['verified', 'unverified'], true)) {
            return null;
        }
        if ($ticket->certificate_issuance_status === 'not_issued') {
            return 'certificate_not_issued';
        }
        if (
            $ticket->certificate_issuance_status !== 'issued' ||
            !in_array($ticket->certificate_validity_status, ['active', 'expired', 'revoked'], true)
        ) {
            return null;
        }
        // Revoke harus diarahkan ke penerbitan ulang sekalipun WA juga belum verified.
        if ($ticket->certificate_validity_status === 'revoked') {
            return 'certificate_revoked';
        }
        if ($ticket->wa_verification_status === 'unverified') {
            return 'unverified';
        }
        if ($ticket->certificate_validity_status === 'expired') {
            return $ticket->layanan === 'Pembaruan' ? 'renewal' : 'service_mismatch';
        }
        if (
            in_array($ticket->recommended_service, ['Reset Passphrase', 'Pembaruan'], true) &&
            $ticket->recommended_service !== $ticket->layanan
        ) {
            return 'service_mismatch';
        }
        if ($this->readyAfterCertificateIssuance($ticket)) {
            return 'ready_for_completion';
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

    /** Notifikasi ditindaklanjuti, kemudian status sertifikat diverifikasi aktif di AMS. */
    private function readyAfterCertificateIssuance(Ticket $ticket): bool {
        if (
            $ticket->activation_status !== 'activated' ||
            $ticket->wa_verification_status !== 'verified' ||
            $ticket->certificate_issuance_status !== 'issued' ||
            $ticket->certificate_validity_status !== 'active'
        ) {
            return false;
        }
        $notes = [
            WaMessageTemplate::EVENTS['certificate_not_issued'],
            WaMessageTemplate::EVENTS['certificate_revoked']
        ];
        if ($ticket->layanan === 'Pembaruan') {
            $notes[] = WaMessageTemplate::EVENTS['renewal'];
        }
        if (
            TicketActivity::query()
                ->where('ticket_id', $ticket->id)
                ->where('action', 'wa_service_confirmed')
                ->whereIn('note', $notes)
                ->exists()
        ) {
            return true;
        }
        // Bukti pemeriksaan AMS yang tercatat tetap sah walaupun notifikasi antara
        // tidak dikirim melalui aplikasi (misalnya petugas menindaklanjuti secara luring).
        return $this->hadCertificateState($ticket, 'revoked') ||
            $this->hadCertificateState($ticket, 'not_issued') ||
            ($ticket->layanan === 'Pembaruan' && $this->hadCertificateState($ticket, 'expired'));
    }

    private function hadCertificateState(Ticket $ticket, string $status): bool {
        $needle = $status === 'not_issued' ? 'Penerbitan SE: not_issued' : 'Status SE: ' . $status;
        return TicketActivity::query()
            ->where('ticket_id', $ticket->id)
            ->where('action', 'ams_checked')
            ->where('note', 'like', '%' . $needle . '%')
            ->exists();
    }

    private function hasConfirmedNotice(Ticket $ticket, string $event): bool {
        return TicketActivity::query()
            ->where('ticket_id', $ticket->id)
            ->where('action', 'wa_service_confirmed')
            ->where('note', WaMessageTemplate::EVENTS[$event])
            ->exists();
    }

    private function hasOnboardingHistory(Ticket $ticket): bool {
        if (
            TicketActivity::query()
                ->where('ticket_id', $ticket->id)
                ->where('action', 'wa_service_confirmed')
                ->whereIn('note', [
                    WaMessageTemplate::EVENTS['unregistered'],
                    WaMessageTemplate::EVENTS['activation_required'],
                    WaMessageTemplate::EVENTS['registration'],
                    WaMessageTemplate::EVENTS['certificate_not_issued'],
                    WaMessageTemplate::EVENTS['certificate_revoked']
                ])
                ->exists()
        ) {
            return true;
        }
        // Pemeriksaan sebelumnya adalah bukti bahwa status penerbitan berubah
        // selama tiket yang sama ditangani, meski notifikasi antara tidak dikonfirmasi.
        return $this->hadCertificateState($ticket, 'not_issued') ||
            $this->hadCertificateState($ticket, 'revoked') ||
            // Perubahan dari belum aktivasi ke SE aktif juga bagian dari onboarding satu tiket.
            TicketActivity::query()
                ->where('ticket_id', $ticket->id)
                ->where('action', 'ams_checked')
                ->where('note', 'like', '%Aktivasi: not_activated%')
                ->exists();
    }

    private function actualProcess(Ticket $ticket, ?string $event): ?string {
        // Nama notifikasi Pendaftaran Diproses tidak berarti penerbitan sudah dilakukan.
        if ($ticket->activation_status === 'not_activated') {
            return 'account_activation';
        }
        return match ($event) {
            'activation_required' => 'account_activation',
            'unverified' => 'whatsapp_verification',
            'certificate_not_issued' => 'set_passphrase',
            'certificate_revoked' => 'certificate_reissuance',
            'service_mismatch', 'issuance_required' => 'service_correction',
            'reset' => 'reset_passphrase',
            'renewal' => 'certificate_renewal',
            'registration' => 'certificate_issuance',
            'ready_for_completion' => $this->hasConfirmedNotice($ticket, 'certificate_revoked') ||
            $this->hadCertificateState($ticket, 'revoked')
                ? 'certificate_reissuance'
                : ($this->hasConfirmedNotice($ticket, 'certificate_not_issued') ||
                $this->hadCertificateState($ticket, 'not_issued')
                    ? 'certificate_issuance'
                    : 'certificate_renewal'),
            default => null
        };
    }

    private function actualOutcome(Ticket $ticket, string $event): string {
        if ($event === 'ready_for_completion') {
            if (
                $this->hasConfirmedNotice($ticket, 'certificate_revoked') ||
                $this->hadCertificateState($ticket, 'revoked')
            ) {
                return 'certificate_reissued';
            }
            if (
                $this->hasConfirmedNotice($ticket, 'certificate_not_issued') ||
                $this->hadCertificateState($ticket, 'not_issued')
            ) {
                return 'certificate_issued';
            }
            if ($this->hasConfirmedNotice($ticket, 'renewal') || $this->hadCertificateState($ticket, 'expired')) {
                return 'certificate_renewed';
            }
        }
        return match ($event) {
            'reset' => 'passphrase_reset',
            'renewal' => 'certificate_renewed',
            'registration' => 'certificate_issued',
            default => 'certificate_issued'
        };
    }

    public function show(Request $request, Ticket $ticket, WhatsAppMessage $whatsapp) {
        $this->verifier($request);
        abort_unless($ticket->status === 'Open' || $ticket->claimed_by === $request->user()->id, 403);
        $ticket->load(['claimant', 'activities.actor', 'whatsappNotifications']);
        $applicantUptd = $ticket->uptd;
        if ($applicantUptd === null && $ticket->source !== 'simulated') {
            $identifier = trim((string) ($ticket->nik ?: $ticket->nip));
            $person = app(\App\Services\EmailDirectoryService::class)->findByIdentifier($identifier);
            if ($person && $ticket->email && strcasecmp((string) $person->email, (string) $ticket->email) === 0) {
                $applicantUptd = $person->uptd;
            }
        }
        $applicantUptd = trim((string) $applicantUptd);
        if ($applicantUptd === '-') {
            $applicantUptd = '';
        }
        $serviceEvent = $ticket->status === 'In Progress' ? $this->serviceEvent($ticket) : null;
        // Pendaftaran Diproses ditutup lewat konfirmasi pengiriman, bukan tombol Tandai Selesai.
        $completionReady =
            !($serviceEvent === 'registration' && $ticket->layanan === 'Pendaftaran') &&
            in_array($serviceEvent, ['reset', 'renewal', 'registration', 'ready_for_completion'], true) &&
            $ticket->activation_status === 'activated' &&
            $ticket->wa_verification_status === 'verified' &&
            $ticket->certificate_issuance_status === 'issued' &&
            $ticket->certificate_validity_status === 'active';
        $serviceMessage =
            $serviceEvent && $serviceEvent !== 'ready_for_completion'
                ? $whatsapp->forServiceTicket($ticket, $serviceEvent)
                : null;
        $serviceNotification = $serviceEvent
            ? $ticket->whatsappNotifications->firstWhere('event_type', $serviceEvent)
            : null;
        $serviceConfirmed = $serviceNotification?->confirmed_at !== null;
        $waMessage = $ticket->status === 'Done' ? $whatsapp->forCompletedTicket($ticket) : null;
        return view(
            'tickets.show',
            compact(
                'ticket',
                'serviceEvent',
                'serviceMessage',
                'serviceNotification',
                'serviceConfirmed',
                'waMessage',
                'applicantUptd',
                'completionReady'
            )
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

    /** Hasil pemeriksaan AMS harus eksplisit; tidak menyimpulkan aktif dari pernah terbit. */
    public function verifyWhatsApp(Request $request, Ticket $ticket) {
        $this->owner($request, $ticket);
        $data = $request->validate([
            'activation_status' => ['required', Rule::in(['activated', 'not_activated'])],
            'wa_verification_status' => ['nullable', Rule::in(['verified', 'unverified'])],
            'certificate_issuance_status' => ['nullable', Rule::in(['issued', 'not_issued'])],
            'certificate_validity_status' => ['nullable', Rule::in(['active', 'expired', 'revoked'])],
            'service_alignment_status' => ['nullable', Rule::in(['suitable', 'unsuitable'])],
            'recommended_service' => ['nullable', Rule::in(['Reset Passphrase', 'Pembaruan'])],
            'wa_verification_note' => ['nullable', 'string', 'max:500']
        ]);
        $activation = $data['activation_status'];
        $wa = $data['wa_verification_status'] ?? null;
        $issuance = $data['certificate_issuance_status'] ?? null;
        $validity = $data['certificate_validity_status'] ?? null;
        $alignment = $data['service_alignment_status'] ?? null;

        if ($activation === 'not_activated') {
            if ($wa === 'verified' || $issuance !== null || $validity !== null || $alignment !== null) {
                throw ValidationException::withMessages([
                    'activation_status' => 'Akun belum aktivasi: pemeriksaan lanjutan belum dapat disimpulkan.'
                ]);
            }
            $wa = 'unchecked';
        } else {
            if ($wa === null) {
                throw ValidationException::withMessages([
                    'wa_verification_status' => 'Periksa verifikasi nomor WA setelah memastikan akun aktif.'
                ]);
            }
            if ($issuance === null) {
                throw ValidationException::withMessages([
                    'certificate_issuance_status' => 'Periksa apakah sertifikat sudah/pernah terbit.'
                ]);
            }
            if ($issuance === 'issued' && $validity === null) {
                throw ValidationException::withMessages([
                    'certificate_validity_status' => 'Periksa status sertifikat: Aktif, Expired, atau Revoke.'
                ]);
            }
            if ($issuance === 'not_issued' && $validity !== null) {
                throw ValidationException::withMessages([
                    'certificate_validity_status' => 'Sertifikat belum terbit tidak memiliki status masa berlaku.'
                ]);
            }
        }

        DB::transaction(function () use ($request, $ticket, $data, $activation, $wa, $issuance, $validity, $alignment) {
            $record = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->claimed_by === $request->user()->id && $record->status === 'In Progress', 409);
            $priorCheck = TicketActivity::query()
                ->where('ticket_id', $record->id)
                ->where('action', 'ams_checked')
                ->exists();
            $priorIssuance = $record->certificate_issuance_status;
            $priorValidity = $record->certificate_validity_status;
            $isExisting = in_array($record->layanan, ['Reset Passphrase', 'Pembaruan'], true);
            $issuedVerified = $activation === 'activated' && $wa === 'verified' && $issuance === 'issued';
            $mismatch =
                ($issuedVerified && $validity === 'expired' && $record->layanan !== 'Pembaruan') ||
                ($issuedVerified &&
                    $validity === 'active' &&
                    $record->layanan === 'Pendaftaran' &&
                    !$this->hasOnboardingHistory($record));
            $recommended = null;
            if ($activation === 'activated' && $issuance === 'issued' && $validity === 'revoked') {
                $recommended = 'Penerbitan Ulang';
                $alignment = 'unsuitable';
            } elseif ($mismatch) {
                if ($record->layanan !== 'Pembaruan' && $validity === 'expired') {
                    $recommended = 'Pembaruan';
                } elseif ($record->layanan === 'Pendaftaran') {
                    $recommended = $data['recommended_service'] ?? null;
                    if (!in_array($recommended, ['Reset Passphrase', 'Pembaruan'], true)) {
                        throw ValidationException::withMessages([
                            'recommended_service' => 'Pilih layanan setelah klarifikasi kebutuhan pemohon.'
                        ]);
                    }
                } else {
                    $recommended = 'Pendaftaran';
                }
                $alignment = 'unsuitable';
            } elseif ($issuedVerified && $validity === 'active' && $isExisting) {
                $recommended = $data['recommended_service'] ?? null;
                if ($recommended !== null && !in_array($recommended, ['Reset Passphrase', 'Pembaruan'], true)) {
                    throw ValidationException::withMessages([
                        'recommended_service' => 'Rekomendasi hanya boleh Reset Passphrase atau Pembaruan.'
                    ]);
                }
                if ($recommended !== null && $recommended === $record->layanan) {
                    throw ValidationException::withMessages([
                        'recommended_service' => 'Pilih layanan berbeda, atau kosongkan jika sudah sesuai.'
                    ]);
                }
                if ($alignment === 'unsuitable' && $recommended === null) {
                    throw ValidationException::withMessages([
                        'recommended_service' => 'Layanan tidak sesuai: pilih layanan hasil klarifikasi.'
                    ]);
                }
                $continuationMismatch =
                    $record->service_alignment_status === 'unsuitable' &&
                    ($this->hadCertificateState($record, 'revoked') ||
                        $this->hadCertificateState($record, 'not_issued'));
                $alignment = $recommended || $continuationMismatch ? 'unsuitable' : 'suitable';
            } elseif ($activation === 'activated' && $issuance === 'not_issued') {
                // Set passphrase tetap pada tiket ini, walau pilihan semula adalah Reset/Pembaruan.
                $alignment = $record->layanan === 'Pendaftaran' ? 'suitable' : 'unsuitable';
            } elseif ($issuedVerified && $validity === 'expired' && $record->layanan === 'Pembaruan') {
                $alignment = 'suitable';
            } elseif ($issuedVerified && $validity === 'active' && $record->layanan === 'Pendaftaran') {
                $alignment =
                    $record->service_alignment_status === 'unsuitable' && $this->hadCertificateState($record, 'revoked')
                        ? 'unsuitable'
                        : 'suitable';
            } else {
                $alignment = null;
            }
            $legacyCertificate = $issuance === 'issued' && $validity === 'active' ? 'active' : 'inactive';
            $changed =
                $record->activation_status !== $activation ||
                $record->wa_verification_status !== $wa ||
                $record->certificate_issuance_status !== $issuance ||
                $record->certificate_validity_status !== $validity ||
                $record->recommended_service !== $recommended ||
                $record->service_alignment_status !== $alignment;
            $record
                ->forceFill([
                    'activation_status' => $activation,
                    'wa_verification_status' => $wa,
                    'certificate_issuance_status' => $issuance,
                    'certificate_validity_status' => $validity,
                    'certificate_status' => $legacyCertificate,
                    'recommended_service' => $recommended,
                    'service_alignment_status' => $alignment,
                    'wa_verification_note' => $data['wa_verification_note'] ?? null,
                    'wa_checked_at' => now(),
                    'wa_checked_by' => $request->user()->id
                ])
                ->save();
            $event = $this->serviceEvent($record);
            $record->forceFill(['actual_process' => $this->actualProcess($record, $event)])->save();
            if ($changed) {
                WaNotification::where('ticket_id', $record->id)
                    ->whereIn('event_type', array_keys(WaMessageTemplate::EVENTS))
                    ->update(['opened_at' => null, 'confirmed_at' => null]);
            }
            TicketActivity::create([
                'ticket_id' => $record->id,
                'actor_id' => $request->user()->id,
                'action' => 'ams_checked',
                'note' =>
                    ($priorCheck ? 'Pemeriksaan ulang' : 'Pemeriksaan awal') .
                    '; Aktivasi: ' .
                    $activation .
                    '; WA: ' .
                    $wa .
                    '; Penerbitan SE: ' .
                    ($issuance ?? 'Belum diperiksa') .
                    '; Status SE: ' .
                    ($validity ?? 'Belum diperiksa') .
                    ($priorIssuance === 'not_issued' && $issuance === 'issued' && $validity === 'active'
                        ? '; Perubahan SE: belum terbit menjadi aktif pada tiket yang sama'
                        : '') .
                    ($priorIssuance === 'issued' && $priorValidity === 'expired' && $validity === 'active'
                        ? '; Perubahan SE: kedaluwarsa menjadi aktif pada tiket yang sama'
                        : '') .
                    ($priorIssuance === 'issued' && $priorValidity === 'revoked' && $validity === 'active'
                        ? '; Perubahan SE: revoked menjadi aktif pada tiket yang sama'
                        : '') .
                    '; Kesesuaian layanan: ' .
                    ($alignment ?? 'Belum dapat ditentukan') .
                    '; Proses aktual: ' .
                    ($record->actual_process ?? 'Belum tersedia') .
                    '; Keputusan: ' .
                    ($event ?? 'Belum tersedia') .
                    ($recommended ? '; Layanan disarankan: ' . $recommended : '') .
                    (!empty($data['wa_verification_note']) ? '; ' . $data['wa_verification_note'] : '')
            ]);
        });
        return back()->with(
            'success',
            'Pemeriksaan AMS tersimpan: aktivasi, WA, penerbitan, status sertifikat dan kesesuaian layanan.'
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
                    'note' => WaMessageTemplate::EVENTS[$event]
                ]);
            }
            // Penyelesaian administratif Pendaftaran: Notifikasi 4 telah DIKONFIRMASI
            // dikirim verifikator. Ini BUKAN bukti akun sudah aktivasi atau SE sudah terbit.
            // Semua dilakukan pada transaksi dan row lock yang sama untuk mencegah double-close.
            if ($event === 'registration' && $record->layanan === 'Pendaftaran') {
                $record->update([
                    'status' => 'Done',
                    'completed_at' => now(),
                    'resolution_note' => null,
                    'actual_process' => 'registration_followup',
                    'service_outcome' => 'registration_processed'
                ]);
                TicketActivity::create([
                    'ticket_id' => $record->id,
                    'actor_id' => $request->user()->id,
                    'action' => 'completed',
                    'note' =>
                        'Notifikasi Pendaftaran Diproses telah dikonfirmasi; ' .
                        'tiket diselesaikan secara administratif, bukan bukti penerbitan SE.'
                ]);
            }
        });
        if ($ticket->layanan === 'Pendaftaran' && $ticket->fresh()->status === 'Done') {
            return redirect()
                ->route('tickets.show', $ticket)
                ->with(
                    'success',
                    'Notifikasi Pendaftaran Diproses dikonfirmasi. Tiket selesai; kirim Notifikasi Selesai + Survei.'
                );
        }
        return back()->with('success', 'Pengiriman notifikasi dikonfirmasi.');
    }

    public function returnForCorrection(Request $request, Ticket $ticket) {
        $this->owner($request, $ticket);
        abort_if(
            $ticket->source === 'simulated',
            403,
            'Tiket simulasi tidak dapat dikembalikan melalui alur WhatsApp.'
        );
        DB::transaction(function () use ($request, $ticket) {
            $record = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $record->status === 'In Progress' && $record->claimed_by === $request->user()->id,
                409,
                'Hanya tiket yang sedang Anda tangani dapat dikembalikan.'
            );
            $event = $this->serviceEvent($record);
            abort_unless(
                in_array($event, WaMessageTemplate::CORRECTION_EVENTS, true) &&
                    in_array($record->recommended_service, ['Pendaftaran', 'Reset Passphrase', 'Pembaruan'], true),
                409,
                'Kembalikan permohonan hanya jika jenis layanan tidak sesuai.'
            );
            abort_unless(
                WaNotification::where('ticket_id', $record->id)
                    ->where('event_type', $event)
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
                'note' => 'Layanan diajukan: ' . $record->layanan . '; disarankan: ' . $record->recommended_service
            ]);
        });
        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Permohonan dikembalikan dengan status Perlu Pengajuan Ulang.');
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
            // Untuk Notifikasi 4, hanya konfirmasi WA yang boleh menutup tiket Pendaftaran.
            abort_if(
                $event === 'registration' && $record->layanan === 'Pendaftaran',
                409,
                'Kirim Notifikasi Pendaftaran Diproses dan klik Saya Sudah Mengirim Pesan. ' .
                    'Tiket akan otomatis selesai dan tautan survei tersedia.'
            );
            abort_unless(
                in_array($event, ['reset', 'renewal', 'registration', 'ready_for_completion'], true),
                409,
                'Tiket belum dapat diselesaikan. Tindak lanjuti hasil AMS atau pengajuan ulang.'
            );
            abort_unless(
                $record->activation_status === 'activated' &&
                    $record->wa_verification_status === 'verified' &&
                    $record->certificate_issuance_status === 'issued' &&
                    $record->certificate_validity_status === 'active',
                409,
                'Sertifikat harus aktif dan WA terverifikasi sebelum tiket diselesaikan.'
            );
            // Menekan Tandai Selesai adalah konfirmasi manual petugas bahwa tindakan layanan selesai.
            // Hasil aktual disimpan, tidak lagi disimpulkan dari nama layanan awal pada pesan akhir.
            $serviceNotificationConfirmed =
                $event === 'ready_for_completion' ||
                $record->source === 'simulated' ||
                WaNotification::where('ticket_id', $record->id)
                    ->where('event_type', $event)
                    ->whereNotNull('confirmed_at')
                    ->exists();
            $outcome = $this->actualOutcome($record, $event);
            $process = $this->actualProcess($record, $event);
            $record->update([
                'status' => 'Done',
                'completed_at' => now(),
                'resolution_note' => null,
                'actual_process' => $process,
                'service_outcome' => $outcome
            ]);
            TicketActivity::create([
                'ticket_id' => $record->id,
                'actor_id' => $request->user()->id,
                'action' => 'completed',
                'note' =>
                    'Hasil aktual: ' .
                    $record->service_outcome_label .
                    '; Proses: ' .
                    $record->actual_process_label .
                    ($serviceNotificationConfirmed ? '' : '; konfirmasi notifikasi layanan belum tercatat')
            ]);
        });
        return back()->with('success', 'Tiket selesai. Notifikasi akhir akan mengikuti hasil aktual pelayanan.');
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
