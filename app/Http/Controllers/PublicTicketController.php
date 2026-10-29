<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PublicTicketController extends Controller
{
    public function create()
    {
        return response()->view('public.request')->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'layanan' => ['required', Rule::in(['Reset Passphrase', 'Pembaruan', 'Pendaftaran'])],
            'nama_user' => ['required', 'string', 'min:3', 'max:160'],
            'nik' => ['required', 'digits:16'],
            'nip' => ['nullable', 'string', 'max:32'],
            'email' => ['required', 'email', 'max:180', 'regex:/@subang\.go\.id$/i'],
            'no_wa' => ['required', 'string', 'max:30'],
            'opd' => ['required', 'string', 'max:180'],
            'jabatan' => ['required', 'string', 'max:180'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'persetujuan' => ['accepted'],
            'submission_token' => ['required', 'uuid'],
            'website' => ['prohibited'], // honeypot: user should never fill it.
        ], [
            'nik.digits' => 'NIK harus berisi tepat 16 digit.',
            'email.regex' => 'Gunakan alamat email dinas @subang.go.id.',
            'persetujuan.accepted' => 'Persetujuan pemrosesan data wajib dicentang.',
        ]);
        $phone = preg_replace('/\D+/', '', $data['no_wa']);
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }
        if (! preg_match('/^628[0-9]{7,12}$/', $phone)) {
            return back()->withInput($request->except(['nik']))
                ->withErrors(['no_wa' => 'Masukkan nomor WhatsApp Indonesia, contoh 081234567890.']);
        }

        $externalId = 'direct-'.$data['submission_token'];
        $ticket = Ticket::where('external_submission_id', $externalId)->first();
        if ($ticket) {
            return redirect()->route('public.success')->with('submission_success_ticket', $ticket->ticket_number);
        }

        try {
            $ticket = DB::transaction(function () use ($data, $phone, $externalId) {
                $ticket = Ticket::create([
                'external_submission_id' => $externalId,
                    'nama_user' => $data['nama_user'],
                    'nik' => $data['nik'],
                    'nip' => $data['nip'] ?? null,
                    'email' => strtolower($data['email']),
                    'no_wa' => $phone,
                    'opd' => $data['opd'],
                    'jabatan' => $data['jabatan'],
                    'keterangan' => $data['keterangan'] ?? null,
                    'layanan' => $data['layanan'],
                    'status' => 'Open',
                    'source' => 'direct',
                    'wa_verification_status' => 'unchecked',
                ]);
                $ticket->update(['ticket_number' => 'TTE-'.now('Asia/Jakarta')->format('Ymd').'-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT)]);
                return $ticket;
            });
        } catch (QueryException $e) {
            // Two identical posts may race; the DB unique index guarantees idempotency.
            $ticket = Ticket::where('external_submission_id', $externalId)->first();
            if (! $ticket) {
                throw $e;
            }
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
