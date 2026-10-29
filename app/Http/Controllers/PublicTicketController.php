<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\EmailDirectoryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicTicketController extends Controller
{
    public function create()
    {
        return response()->view('public.request')
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Only a masked email is returned; no employee profile or full address is exposed. */
    public function lookupNik(Request $request, EmailDirectoryService $directory)
    {
        $data = $request->validate(['nik' => ['required', 'regex:/^[0-9]{16}$/D']]);
        if (! $directory->available()) {
            return response()->json([
                'status' => 'service_unavailable',
                'message' => 'Direktori email belum tersedia atau belum diperbarui. Silakan coba kembali nanti.',
            ], 503)->header('Cache-Control', 'no-store');
        }
        $record = $directory->find($data['nik']);
        if (! $record || ! $record->email) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Data NIK atau username email dinas belum tersedia. Silakan hubungi Pengelola Email Dinas.',
            ])->header('Cache-Control', 'no-store');
        }
        return response()->json([
            'status' => 'found',
            'email_masked' => $directory->maskedEmail($record->email),
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, EmailDirectoryService $directory)
    {
        $data = $request->validate([
            'layanan' => ['required', Rule::in(['Reset Passphrase', 'Pembaruan', 'Pendaftaran'])],
            'nama_user' => ['required', 'string', 'min:3', 'max:160'],
            'nik' => ['required', 'regex:/^[0-9]{16}$/D'],
            'nip' => ['nullable', 'string', 'max:32'],
            'no_wa' => ['required', 'string', 'max:30'],
            'opd' => ['required', 'string', 'max:180'],
            'jabatan' => ['required', 'string', 'max:180'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'persetujuan' => ['accepted'],
            'signature_data' => ['required', 'string', 'max:240000'],
            'submission_token' => ['required', 'uuid'],
            'website' => ['prohibited'],
        ], [
            'nik.regex' => 'NIK harus berisi tepat 16 digit.',
            'persetujuan.accepted' => 'Persetujuan pemrosesan data wajib dicentang.',
            'signature_data.required' => 'Tanda tangan persetujuan wajib diisi.',
        ]);
        $externalId = 'direct-'.$data['submission_token'];
        if ($existing = Ticket::where('external_submission_id', $externalId)->first()) {
            return redirect()->route('public.success')->with('submission_success_ticket', $existing->ticket_number);
        }
        if (! $directory->available()) {
            throw ValidationException::withMessages(['nik' => 'Direktori email belum siap. Silakan coba kembali nanti.']);
        }
        $person = $directory->find($data['nik']);
        if (! $person || ! $person->email) {
            throw ValidationException::withMessages(['nik' => 'NIK atau username email belum terdaftar. Hubungi Pengelola Email Dinas.']);
        }
        // Never use email supplied by browser; the authoritative value comes from the directory.
        $phone = preg_replace('/\D+/', '', $data['no_wa']);
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }
        if (! preg_match('/^628[0-9]{7,12}$/D', $phone)) {
            throw ValidationException::withMessages(['no_wa' => 'Nomor WhatsApp Indonesia tidak valid.']);
        }
        if (! str_starts_with($data['signature_data'], 'data:image/png;base64,')) {
            throw ValidationException::withMessages(['signature_data' => 'Tanda tangan harus berupa PNG dari kolom tanda tangan.']);
        }
        $bytes = base64_decode(substr($data['signature_data'], strlen('data:image/png;base64,')), true);
        $image = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
        if (! $image || ($image['mime'] ?? '') !== 'image/png' || strlen($bytes) > 180000
            || $image[0] < 300 || $image[0] > 1200 || $image[1] < 100 || $image[1] > 500) {
            throw ValidationException::withMessages(['signature_data' => 'Tanda tangan tidak valid. Silakan gambar ulang.']);
        }
        $path = 'signatures/'.Str::uuid().'.png';
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new \RuntimeException('Gagal menyimpan tanda tangan secara privat.');
        }
        try {
            $ticket = DB::transaction(function () use ($data, $phone, $externalId, $person, $path) {
                $ticket = Ticket::create([
                    'external_submission_id' => $externalId,
                    'nama_user' => $data['nama_user'], 'nik' => $data['nik'],
                    'nip' => $data['nip'] ?? null, 'email' => $person->email,
                    'no_wa' => $phone, 'opd' => $data['opd'], 'jabatan' => $data['jabatan'],
                    'keterangan' => $data['keterangan'] ?? null,
                    'layanan' => $data['layanan'], 'status' => 'Open', 'source' => 'direct',
                    'wa_verification_status' => 'unchecked',
                    'consent_accepted_at' => now(), 'signature_path' => $path,
                ]);
                $ticket->update(['ticket_number' => 'TTE-'.now('Asia/Jakarta')->format('Ymd').'-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT)]);
                return $ticket;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            if ($e instanceof QueryException && $ticket = Ticket::where('external_submission_id', $externalId)->first()) {
                return redirect()->route('public.success')->with('submission_success_ticket', $ticket->ticket_number);
            }
            throw $e;
        }
        return redirect()->route('public.success')->with('submission_success_ticket', $ticket->ticket_number);
    }

    public function success(Request $request)
    {
        $ticketNumber = $request->session()->get('submission_success_ticket');
        if (! $ticketNumber) {
            return redirect()->route('public.form');
        }
        return response()->view('public.success', compact('ticketNumber'))
            ->withHeaders(['Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
