<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
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
            'done' => Ticket::where('status', 'Done')->count()
        ];
        $services = Ticket::select('layanan')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('layanan')
            ->pluck('total', 'layanan');
        $counts = Ticket::select('claimed_by')
            ->selectRaw(
                "COUNT(*) as total, SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) as done_count, SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as active_count"
            )
            ->whereNotNull('claimed_by')
            ->groupBy('claimed_by')
            ->get()
            ->keyBy('claimed_by');
        $verifiers = User::where('role', 'verifier')->orderBy('name')->get();

        return view('admin.dashboard', compact('stats', 'services', 'counts', 'verifiers'));
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
            'updated_at' => now()
        ]);

        return back()->with(
            'success',
            'Tautan reset password dibuat untuk ' .
                $user->name .
                '. Pada MAIL_MAILER=log, periksa storage/logs/laravel.log.'
        );
    }
}
