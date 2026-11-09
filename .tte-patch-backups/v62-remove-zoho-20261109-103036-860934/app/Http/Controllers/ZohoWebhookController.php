<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\ActiveTicketGuard;
use App\Services\NewTicketEmailNotifier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ZohoWebhookController extends Controller {
    public function store(Request $request, NewTicketEmailNotifier $notifier, ActiveTicketGuard $guard) {
        $secret = config('tte.zoho_webhook_secret');
        if (!is_string($secret) || strlen($secret) < 24) {
            return response()->json(['message' => 'Integrasi belum dikonfigurasi.'], 503);
        }
        if (!hash_equals($secret, (string) $request->header('X-Zoho-Webhook-Secret', ''))) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Hanya menerima JSON. Map label field Zoho ke key ini pada pengaturan webhook.
        if (!$request->isJson()) {
            return response()->json(['message' => 'Content-Type harus application/json'], 415);
        }
        $data = $request->validate([
            'submission_id' => ['required', 'string', 'max:128'],
            'nama_user' => ['required', 'string', 'max:160'],
            'no_wa' => ['required', 'string', 'max:30'],
            'layanan' => ['required', Rule::in(['Reset Passphrase', 'Pembaruan', 'Pendaftaran'])],
            'nip' => ['nullable', 'string', 'max:32'],
            'nik' => ['nullable', 'digits:16'],
            'opd' => ['nullable', 'string', 'max:180'],
            'jabatan' => ['nullable', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180'],
            'keterangan' => ['nullable', 'string', 'max:2000']
        ]);
        $phone = preg_replace('/\D+/', '', $data['no_wa']);
        if (Str::startsWith($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        if (!preg_match('/^62[0-9]{8,13}$/', $phone)) {
            return response()->json(['message' => 'Nomor WhatsApp tidak valid (Indonesia).'], 422);
        }

        $digest = hash('sha256', (string) $request->getContent());
        $externalId = $data['submission_id'];
        $existing = Ticket::where('external_submission_id', $externalId)->first();
        if ($existing) {
            $this->log($externalId, 'duplicate', $digest);
            return response()->json(['ticket_number' => $existing->ticket_number, 'duplicate' => true]);
        }

        $fingerprints = $guard->fingerprints($data['nik'] ?? null, $data['nip'] ?? null);
        return $guard->synchronized($fingerprints, function () use (
            $guard, $fingerprints, $data, $externalId, $phone, $digest, $notifier
        ) {
            if ($existing = Ticket::where('external_submission_id', $externalId)->first()) {
                $this->log($externalId, 'duplicate', $digest);
                return response()->json(['ticket_number' => $existing->ticket_number, 'duplicate' => true]);
            }
            if ($active = $guard->active($fingerprints)) {
                $guard->blocked($active, $externalId, 'zoho', $data['layanan']);
                $this->log($externalId, 'active_blocked', $digest);
                // HTTP 200 acknowledges the webhook so the source does not retry forever.
                return response()->json([
                    'ticket_number' => $active->ticket_number,
                    'duplicate' => true,
                    'blocked_active' => true
                ]);
            }
            try {
                $ticket = DB::transaction(function () use ($data, $externalId, $phone, $fingerprints) {
                    $ticket = Ticket::create([
                        'external_submission_id' => $externalId,
                        'nama_user' => $data['nama_user'],
                        'no_wa' => $phone,
                        'layanan' => $data['layanan'],
                        'nip' => $data['nip'] ?? null,
                        'nik' => $data['nik'] ?? null,
                        'applicant_nik_hash' => $fingerprints['nik'],
                        'applicant_nip_hash' => $fingerprints['nip'],
                        'opd' => $data['opd'] ?? null,
                        'jabatan' => $data['jabatan'] ?? null,
                        'email' => $data['email'] ?? null,
                        'keterangan' => $data['keterangan'] ?? null,
                        'status' => 'Open',
                        'source' => 'zoho'
                    ]);
                    $ticket->update([
                        'ticket_number' => 'TTE-' . now('Asia/Jakarta')->format('Ymd') . '-' .
                            str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT)
                    ]);
                    return $ticket;
                });
            } catch (QueryException $e) {
                $existing = Ticket::where('external_submission_id', $externalId)->first();
                if (!$existing) {
                    throw $e;
                }
                $this->log($externalId, 'duplicate', $digest);
                return response()->json(['ticket_number' => $existing->ticket_number, 'duplicate' => true]);
            }
            $this->log($externalId, 'created', $digest);
            $notifier->notify($ticket);
            return response()->json(['ticket_number' => $ticket->ticket_number, 'duplicate' => false], 201);
        });
    }

    private function log(string $externalId, string $outcome, string $digest): void {
        DB::table('webhook_events')->insert([
            'external_submission_id' => $externalId,
            'outcome' => $outcome,
            'payload_sha256' => $digest,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
