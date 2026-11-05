<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\EmailDirectoryService;
use App\Services\NewTicketEmailNotifier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicTicketController extends Controller {
    public function create() {
        return response()
            ->view('public.request')
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Public lookup returns directory details after a rate-limited NIK/NIP check.
     * The browser may correct profile fields, but email remains authoritative server-side.
     * WARNING: Full profile exposure by NIK requires additional anti-enumeration controls in production.
     */
    public function lookupNik(Request $request, EmailDirectoryService $directory) {
        $data = $request->validate(['nik' => ['required', 'regex:/\A(?:[0-9]{16}|[0-9]{18})\z/']]);
        if (!$directory->available()) {
            return response()
                ->json(
                    [
                        'status' => 'service_unavailable',
                        'message' => 'Direktori email belum tersedia atau belum diperbarui. Silakan coba kembali nanti.'
                    ],
                    503
                )
                ->header('Cache-Control', 'private, no-store, max-age=0')
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }
        $record = $directory->findByIdentifier($data['nik']);
        if (!$record || !$record->email) {
            return response()
                ->json([
                    'status' => 'not_found',
                    'message' =>
                        'NIK/NIP atau username email dinas belum tersedia. Coba NIP 18 digit atau hubungi Pengelola Email Dinas.'
                ])
                ->header('Cache-Control', 'private, no-store, max-age=0')
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }
        return response()
            ->json([
                'status' => 'found',
                // Email is deliberately displayed in full per the service's form requirement.
                // Do not trust any email supplied on the submission endpoint.
                'email' => (string) $record->email,
                'nama_user' => (string) ($record->nama ?? ''),
                'nip' => (string) ($record->nip ?? ''),
                'jabatan' => (string) ($record->jabatan ?? ''),
                'opd' => (string) ($record->unit_kerja ?: $record->nama_unor ?? ''),
                'uptd' => trim((string) ($record->uptd ?? ''))
            ])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request, EmailDirectoryService $directory, NewTicketEmailNotifier $notifier) {
        $data = $request->validate(
            [
                'layanan' => ['required', Rule::in(['Reset Passphrase', 'Pembaruan', 'Pendaftaran'])],
                'nama_user' => ['required', 'string', 'min:3', 'max:160'],
                'nik' => ['required', 'regex:/\A(?:[0-9]{16}|[0-9]{18})\z/'],
                'nip' => ['nullable', 'string', 'max:32'],
                'no_wa' => ['required', 'string', 'regex:/\A(?:8[0-9]{8,12}|08[0-9]{8,12}|628[0-9]{8,12})\z/'],
                'opd' => ['required', 'string', 'max:180'],
                'jabatan' => ['required', 'string', 'max:180'],
                'keterangan' => ['nullable', 'string', 'max:2000'],
                'persetujuan' => ['accepted'],
                'signature_data' => ['required', 'string', 'max:240000'],
                'submission_token' => ['required', 'uuid'],
                'website' => ['prohibited']
            ],
            [
                'nik.regex' => 'Masukkan NIK 16 digit atau NIP 18 digit, hanya angka.',
                'no_wa.regex' => 'Nomor WhatsApp harus angka saja, dimulai dengan 8 (9–13 digit) setelah kode +62.',
                'persetujuan.accepted' => 'Persetujuan pemrosesan data wajib dicentang.',
                'signature_data.required' => 'Tanda tangan persetujuan wajib diisi.'
            ]
        );
        $externalId = 'direct-' . $data['submission_token'];
        if ($existing = Ticket::where('external_submission_id', $externalId)->first()) {
            return redirect()->route('public.success')->with('submission_success_ticket', $existing->ticket_number);
        }
        if (!$directory->available()) {
            throw ValidationException::withMessages([
                'nik' => 'Direktori email belum siap. Silakan coba kembali nanti.'
            ]);
        }
        $person = $directory->findByIdentifier($data['nik']);
        if (!$person || !$person->email) {
            throw ValidationException::withMessages([
                'nik' => 'NIK/NIP atau username email belum terdaftar. Hubungi Pengelola Email Dinas.'
            ]);
        }
        // Never use email supplied by browser; the authoritative value comes from the directory.
        // Input baru: 8..., format yang disimpan selalu 628...
        // Tetap kompatibel dengan klien lama yang mengirim 08... atau 628...
        $phone = $data['no_wa'];
        if (str_starts_with($phone, '08')) {
            $phone = '62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }
        if (!preg_match('/\A628[0-9]{8,12}\z/', $phone)) {
            throw ValidationException::withMessages(['no_wa' => 'Nomor WhatsApp Indonesia tidak valid.']);
        }
        if (!str_starts_with($data['signature_data'], 'data:image/png;base64,')) {
            throw ValidationException::withMessages([
                'signature_data' => 'Tanda tangan harus berupa PNG dari kolom tanda tangan.'
            ]);
        }
        $bytes = base64_decode(substr($data['signature_data'], strlen('data:image/png;base64,')), true);
        $image = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
        if (
            !$image ||
            ($image['mime'] ?? '') !== 'image/png' ||
            strlen($bytes) > 180000 ||
            $image[0] < 300 ||
            $image[0] > 1200 ||
            $image[1] < 100 ||
            $image[1] > 500
        ) {
            throw ValidationException::withMessages([
                'signature_data' => 'Tanda tangan tidak valid. Silakan gambar ulang.'
            ]);
        }
        $path = 'signatures/' . Str::uuid() . '.png';
        if (!Storage::disk('local')->put($path, $bytes)) {
            throw new \RuntimeException('Gagal menyimpan tanda tangan secara privat.');
        }
        try {
            $ticket = DB::transaction(function () use ($data, $phone, $externalId, $person, $path) {
                $ticket = Ticket::create([
                    'external_submission_id' => $externalId,
                    'nama_user' => $data['nama_user'],
                    'nik' => $person->nik ?: null,
                    'nip' => $data['nip'] ?: $person->nip,
                    'email' => $person->email,
                    'no_wa' => $phone,
                    'opd' => $data['opd'],
                    // Nilai direktori disalin saat pengajuan; input UPTD dari browser diabaikan.
                    // String kosong berarti pemohon diketahui tidak mempunyai UPTD.
                    'uptd' => trim((string) ($person->uptd ?? '')),
                    'jabatan' => $data['jabatan'],
                    'keterangan' => $data['keterangan'] ?? null,
                    'layanan' => $data['layanan'],
                    'status' => 'Open',
                    'source' => 'direct',
                    'wa_verification_status' => 'unchecked',
                    'consent_accepted_at' => now(),
                    'signature_path' => $path
                ]);
                $ticket->update([
                    'ticket_number' =>
                        'TTE-' .
                        now('Asia/Jakarta')->format('Ymd') .
                        '-' .
                        str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT)
                ]);
                return $ticket;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            if (
                $e instanceof QueryException &&
                ($ticket = Ticket::where('external_submission_id', $externalId)->first())
            ) {
                return redirect()->route('public.success')->with('submission_success_ticket', $ticket->ticket_number);
            }
            throw $e;
        }
        $notifier->notify($ticket);
        return redirect()->route('public.success')->with('submission_success_ticket', $ticket->ticket_number);
    }

    public function success(Request $request) {
        $ticketNumber = $request->session()->get('submission_success_ticket');
        if (!$ticketNumber) {
            return redirect()->route('public.form');
        }
        return response()
            ->view('public.success', compact('ticketNumber'))
            ->withHeaders(['Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
