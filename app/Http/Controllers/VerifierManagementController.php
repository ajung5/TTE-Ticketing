<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class VerifierManagementController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Hanya admin yang dapat mengelola verifikator.');
    }

    private function authorizeVerifier(User $verifier): void
    {
        abort_unless($verifier->isVerifier(), 404, 'Akun verifikator tidak ditemukan.');
    }

    private function passwordRule(): Password
    {
        return Password::min(12)->mixedCase()->numbers()->symbols();
    }

    private function audit(Request $request, User $verifier, string $action): void
    {
        DB::table('audit_logs')->insert([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $verifier->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function expireDatabaseSessions(User $verifier): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $verifier->id)
                ->delete();
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $verifiers = User::query()
            ->where('role', 'verifier')
            ->orderBy('name')
            ->get();

        $counts = Ticket::query()
            ->select('claimed_by')
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as active_count, SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) as done_count")
            ->whereNotNull('claimed_by')
            ->groupBy('claimed_by')
            ->get()
            ->keyBy('claimed_by');

        return response()->view('admin.verifiers', compact('verifiers', 'counts'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'initials' => ['nullable', 'alpha_num', 'max:12'],
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ]);

        $reactivated = false;
        DB::transaction(function () use ($request, $data, &$reactivated) {
            // Email yang pernah dihapus direaktivasi, agar identitas dan histori audit tetap sama.
            $verifier = User::onlyTrashed()->where('email', $data['email'])->first();
            if ($verifier !== null) {
                abort_unless($verifier->isVerifier(), 409);
                $reactivated = true;
                $verifier->restore();
            } else {
                $verifier = new User();
            }

            $verifier->fill([
                'name' => trim($data['name']),
                'email' => $data['email'],
                'initials' => strtoupper((string) ($data['initials'] ?? '')) ?: null,
                'role' => 'verifier',
                'password' => $data['password'], // hashed oleh cast User
            ]);
            // remember_token tidak ada di fillable, tetapkan secara eksplisit.
            $verifier->remember_token = Str::random(60);
            $verifier->save();
            $this->expireDatabaseSessions($verifier);
            $this->audit($request, $verifier, $reactivated ? 'admin_reactivated_verifier' : 'admin_created_verifier');
        });

        return redirect()->route('admin.verifiers.index')
            ->with('success', $reactivated ? 'Akun verifikator lama telah diaktifkan kembali.' : 'Akun verifikator berhasil ditambahkan.');
    }

    public function update(Request $request, User $verifier)
    {
        $this->authorizeAdmin($request);
        $this->authorizeVerifier($verifier);
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($verifier->id)],
            'initials' => ['nullable', 'alpha_num', 'max:12'],
            'password' => ['nullable', 'confirmed', $this->passwordRule()],
        ]);

        DB::transaction(function () use ($request, $verifier, $data) {
            $verifier = User::whereKey($verifier->id)->lockForUpdate()->firstOrFail();
            $verifier->fill([
                'name' => trim($data['name']),
                'email' => $data['email'],
                'initials' => strtoupper((string) ($data['initials'] ?? '')) ?: null,
            ]);
            if (!empty($data['password'])) {
                $verifier->password = $data['password']; // hashed otomatis oleh model
                $verifier->remember_token = Str::random(60);
            }
            $verifier->save();
            if (!empty($data['password'])) {
                $this->expireDatabaseSessions($verifier);
            }
            $this->audit($request, $verifier, empty($data['password']) ? 'admin_updated_verifier' : 'admin_changed_verifier_password');
        });

        return redirect()->route('admin.verifiers.index')
            ->with('success', 'Profil verifikator berhasil diperbarui'.(empty($data['password']) ? '.' : ' dan password diganti.'));
    }

    public function destroy(Request $request, User $verifier)
    {
        $this->authorizeAdmin($request);
        $this->authorizeVerifier($verifier);

        DB::transaction(function () use ($request, $verifier) {
            $verifier = User::whereKey($verifier->id)->lockForUpdate()->firstOrFail();
            if (Ticket::where('claimed_by', $verifier->id)->where('status', 'In Progress')->exists()) {
                throw ValidationException::withMessages([
                    'verifier' => 'Verifikator masih menangani tiket aktif. Selesaikan atau alihkan tiket tersebut sebelum menghapus akun.',
                ]);
            }

            $this->audit($request, $verifier, 'admin_deleted_verifier');
            // Soft delete: mencegah login tetapi mempertahankan nama pada jejak audit.
            $verifier->delete();
            $this->expireDatabaseSessions($verifier);
        });

        return redirect()->route('admin.verifiers.index')
            ->with('success', 'Akun verifikator dihapus dari daftar aktif; riwayat penanganan tetap tersimpan.');
    }
}
