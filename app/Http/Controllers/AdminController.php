<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Services\TteSystemHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class AdminController extends Controller {
    private function check(Request $request): void {
        abort_unless($request->user()->isAdmin(), 403, 'Akses khusus admin.');
    }

    public function dashboard(Request $request) {
        $this->check($request);
        $stats = [
            'total' => Ticket::count(),
            'new' => Ticket::where('status', 'Open')->count(),
            'active' => Ticket::where('status', 'In Progress')->count(),
            'done' => Ticket::where('status', 'Done')->count(),
            'returned' => Ticket::where('status', 'Returned')->count(),
        ];
        $ams = Ticket::query()
            ->selectRaw(
                "COUNT(*) as total,
            SUM(CASE WHEN activation_status = 'activated' THEN 1 ELSE 0 END) as activated,
            SUM(CASE WHEN activation_status = 'not_activated' THEN 1 ELSE 0 END) as not_activated,
            SUM(CASE WHEN activation_status = 'activated' AND wa_verification_status = 'unverified' THEN 1 ELSE 0 END) as wa_unverified,
            SUM(CASE WHEN activation_status = 'activated' AND wa_verification_status = 'verified' THEN 1 ELSE 0 END) as wa_verified,
            SUM(CASE WHEN certificate_issuance_status = 'not_issued' THEN 1 ELSE 0 END) as not_issued,
            SUM(CASE WHEN certificate_issuance_status = 'issued' AND certificate_validity_status = 'active' THEN 1 ELSE 0 END) as cert_active,
            SUM(CASE WHEN certificate_issuance_status = 'issued' AND certificate_validity_status = 'expired' THEN 1 ELSE 0 END) as cert_expired,
            SUM(CASE WHEN certificate_issuance_status = 'issued' AND certificate_validity_status = 'revoked' THEN 1 ELSE 0 END) as cert_revoked",
            )
            ->first();
        $services = Ticket::select('layanan')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('layanan')
            ->pluck('total', 'layanan');
        $counts = Ticket::select('claimed_by')
            ->selectRaw(
                "COUNT(*) as total, SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) as done_count,
            SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN status = 'Returned' THEN 1 ELSE 0 END) as returned_count",
            )
            ->whereNotNull('claimed_by')
            ->groupBy('claimed_by')
            ->get()
            ->keyBy('claimed_by');
        $verifiers = User::where('role', 'verifier')->orderBy('name')->get();
        $health = app(TteSystemHealth::class)->summary();
        $attentionHours = max(1, (int) config('tte.ticket_attention_hours', 24));
        $attention = [
            'hours' => $attentionHours,
            'waiting' => Ticket::where('status', 'Open')
                ->where('created_at', '<', now()->subHours($attentionHours))
                ->count(),
            'idle' => Ticket::where('status', 'In Progress')
                ->where('updated_at', '<', now()->subHours($attentionHours))
                ->count(),
            'weekly_in' => Ticket::where('created_at', '>=', now()->subDays(7))->count(),
            'weekly_done' => Ticket::where('status', 'Done')
                ->where('completed_at', '>=', now()->subDays(7))
                ->count(),
        ];
        // Distribusi hasil aktual, berbeda dengan distribusi layanan yang diminta pemohon.
        $outcomes = Ticket::query()
            ->where('status', 'Done')
            ->whereNotNull('service_outcome')
            ->select('service_outcome')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('service_outcome')
            ->pluck('total', 'service_outcome');
        return view(
            'admin.dashboard',
            compact(
                'stats',
                'services',
                'counts',
                'verifiers',
                'ams',
                'outcomes',
                'health',
                'attention',
            ),
        );
    }

    public function sendResetLink(Request $request, User $user) {
        $this->check($request);
        abort_unless($user->isVerifier(), 403, 'Reset dari halaman admin hanya untuk verifikator.');
        $status = Password::broker()->sendResetLink(['email' => $user->email]);
        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['reset' => __($status)]);
        }
        DB::table('audit_logs')->insert([
            'actor_id' => $request->user()->id,
            'action' => 'admin_sent_password_reset',
            'target_type' => 'user',
            'target_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with(
            'success',
            'Tautan reset password dibuat untuk ' .
                $user->name .
                '. Periksa inbox email akun tersebut. Pengiriman memerlukan SMTP aktif.',
        );
    }
}
