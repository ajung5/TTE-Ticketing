<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Admin is authorized to reassign work, never to read private ticket detail or process AMS. */
class AdminTicketTransferController extends Controller {
    private function admin(Request $request): void {
        abort_unless($request->user()?->isAdmin(), 403, 'Akses khusus admin.');
    }

    public function index(Request $request) {
        $this->admin($request);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'owner' => ['nullable', 'integer', 'min:1']]);
        $q = trim((string) ($data['q'] ?? ''));
        $tickets = Ticket::query()
            ->with('claimant')
            ->where('status', 'In Progress')
            ->whereNotNull('claimed_by')
            ->when($data['owner'] ?? null, fn ($query, $owner) => $query->where('claimed_by', $owner))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('ticket_number', 'like', '%' . $q . '%')
                        ->orWhere('nama_user', 'like', '%' . $q . '%');
                });
            })
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();
        $verifiers = User::query()->where('role', 'verifier')->orderBy('name')->get();
        $loads = Ticket::where('status', 'In Progress')->whereNotNull('claimed_by')
            ->selectRaw('claimed_by, COUNT(*) as total')->groupBy('claimed_by')->pluck('total', 'claimed_by');
        return response()->view('admin.ticket-transfers', compact('tickets', 'verifiers', 'q', 'loads'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function transfer(Request $request, Ticket $ticket) {
        $this->admin($request);
        $request->merge(['transfer_ticket' => $ticket->id]);
        $data = $request->validate([
            'expected_owner' => ['sometimes', 'required', 'integer'],
            'verifier_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'verifier')->whereNull('deleted_at')),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $reason = trim($data['reason']);
        if (mb_strlen($reason) < 5) {
            return back()->withInput()->withErrors(['reason' => 'Alasan pengalihan minimal 5 karakter.']);
        }

        DB::transaction(function () use ($ticket, $request, $data, $reason) {
            $record = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'In Progress' && $record->claimed_by !== null,
                409, 'Hanya tiket yang sedang diproses dapat dialihkan.');
            if (isset($data['expected_owner']) && (int) $data['expected_owner'] !== (int) $record->claimed_by) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'verifier_id' => 'Penanggung jawab telah berubah. Periksa petugas sekarang sebelum mengalihkan.',
                ]);
            }
            $newOwner = User::query()->whereKey($data['verifier_id'])
                ->where('role', 'verifier')->lockForUpdate()->first();
            abort_unless($newOwner !== null, 409, 'Verifikator tujuan tidak aktif.');
            abort_unless((int) $record->claimed_by !== (int) $newOwner->id,
                409, 'Tiket sudah ditangani oleh verifikator yang dipilih.');
            $previousId = $record->claimed_by;
            $previousName = $record->claimant?->name ?? ('User #' . $previousId);
            $record->forceFill([
                'claimed_by' => $newOwner->id,
                'claimed_at' => now(),
            ])->save();
            TicketActivity::create([
                'ticket_id' => $record->id,
                'actor_id' => $request->user()->id,
                'action' => 'admin_reassigned',
                'note' => 'Dari ' . $previousName . ' (#' . $previousId . ') ke ' .
                    $newOwner->name . ' (#' . $newOwner->id . '). Alasan: ' . $reason,
            ]);
            DB::table('audit_logs')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'admin_reassigned_ticket',
                'target_type' => 'ticket',
                'target_id' => $record->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        return redirect()->route('admin.transfers.index')
            ->with('success', 'Tiket berhasil dialihkan. Nomor dan riwayat penanganan tetap sama.');
    }
}
