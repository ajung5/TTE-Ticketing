<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketActivity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Fingerprints are keyed, never raw NIK/NIP, and are evaluated for ALL services.
 * Requests without a trusted identifier cannot be reliably deduplicated;
 * do not fall back to a user-controlled name or phone.
 * Atomic cache locks must use a SHARED driver across web workers/servers.
 */
class ActiveTicketGuard {
    public function __construct(private EmailDirectoryService $directory) {}

    public function fingerprints(?string $nik, ?string $nip): array {
        $nik = trim((string) $nik);
        $nip = trim((string) $nip);
        return [
            'nik' => preg_match('/\A[0-9]{16}\z/', $nik) ? $this->directory->nikHash($nik) : null,
            'nip' => preg_match('/\A[0-9]{18}\z/', $nip) ? $this->directory->nipOnlyHash($nip) : null
        ];
    }

    public function synchronized(array $fingerprints, callable $callback): mixed {
        // Prefer NIP as the stable lock identifier for submissions by NIK or NIP.
        $identifier = $fingerprints['nip'] ?? $fingerprints['nik'] ?? null;
        if (!$identifier) {
            return $callback();
        }
        return Cache::lock('tte-active-applicant:' . $identifier, 30)->block(6, $callback);
    }

    public function active(array $fingerprints): ?Ticket {
        $nik = $fingerprints['nik'] ?? null;
        $nip = $fingerprints['nip'] ?? null;
        if (!$nik && !$nip) {
            return null;
        }
        return Ticket::query()
            ->whereIn('status', ['Open', 'In Progress'])
            ->where('source', '!=', 'simulated')
            ->where(function ($query) use ($nik, $nip) {
                if ($nik) {
                    $query->where('applicant_nik_hash', $nik);
                }
                if ($nip) {
                    $nik ? $query->orWhere('applicant_nip_hash', $nip)
                         : $query->where('applicant_nip_hash', $nip);
                }
            })
            ->orderBy('id')
            ->first();
    }

    /** Idempotent per submission ID, and never extends the work-idle clock. */
    public function blocked(Ticket $ticket, string $externalId, string $source, string $service): void {
        DB::transaction(function () use ($ticket, $externalId, $source, $service) {
            $created = DB::table('blocked_ticket_attempts')->insertOrIgnore([
                'ticket_id' => $ticket->id,
                'external_submission_id' => $externalId,
                'source' => $source,
                'requested_service' => $service,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            if ($created !== 1) {
                return;
            }
            DB::table('tickets')->where('id', $ticket->id)->update([
                'duplicate_attempts' => DB::raw('duplicate_attempts + 1'),
                'last_duplicate_attempt_at' => now()
            ]);
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'actor_id' => null,
                'action' => 'duplicate_request_blocked',
                'note' => 'Sistem menahan permohonan baru dari ' . $source .
                    '; layanan diminta: ' . $service .
                    '. Pemohon diarahkan menindaklanjuti tiket aktif.'
            ]);
        });
    }
}
