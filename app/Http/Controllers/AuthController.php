<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\Rule;

class AuthController extends Controller {
    public function form() {
        return view('auth.login');
    }

    public function login(Request $request) {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (!Auth::attempt($credentials)) {
            return back()
                ->withErrors(['email' => 'Email atau password tidak sesuai.'])
                ->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function profile(Request $request) {
        return view('auth.profile', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request) {
        $user = $request->user();

        abort_unless($user?->isAdmin(), 403, 'Hanya administrator yang dapat mengubah profil ini.');

        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => Str::lower(trim((string) $request->input('email'))),
            'initials' => Str::upper(trim((string) $request->input('initials')))
        ]);

        $emailChanged = $request->input('email') !== Str::lower($user->email);

        $data = $request->validate(
            [
                'name' => ['required', 'string', 'min:3', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
                'initials' => ['nullable', 'alpha_num', 'max:12'],
                'current_password' => $emailChanged ? ['required', 'current_password'] : ['nullable']
            ],
            [
                'name.required' => 'Nama lengkap wajib diisi.',
                'name.min' => 'Nama lengkap minimal 3 karakter.',
                'email.required' => 'Alamat email wajib diisi.',
                'email.email' => 'Format email tidak valid.',
                'email.unique' => 'Email sudah digunakan akun lain.',
                'initials.alpha_num' => 'Inisial hanya boleh berisi huruf dan angka.',
                'initials.max' => 'Inisial maksimal 12 karakter.',
                'current_password.required' => 'Password saat ini wajib diisi untuk mengubah email.',
                'current_password.current_password' => 'Password saat ini tidak sesuai.'
            ]
        );

        DB::transaction(function () use ($user, $data, $emailChanged) {
            $oldEmail = $user->email;

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'initials' => $data['initials'] ?: null
            ]);

            if ($emailChanged) {
                $user->email_verified_at = null;
                $user->remember_token = Str::random(60);
            }

            $user->save();

            if ($emailChanged) {
                DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
            }

            DB::table('audit_logs')->insert([
                'actor_id' => $user->id,
                'action' => 'admin_updated_own_profile',
                'target_type' => 'user',
                'target_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        });

        $request->session()->regenerate();

        return redirect()->route('profile')->with('success', 'Profil administrator berhasil diperbarui.');
    }

    public function changePassword(Request $request) {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()]
        ]);
        $request
            ->user()
            ->forceFill([
                'password' => Hash::make($data['password']),
                'remember_token' => Str::random(60)
            ])
            ->save();
        $request->session()->regenerate();

        return back()->with('success', 'Password Anda telah diperbarui.');
    }

    public function forgotForm() {
        return response()->view('auth.forgot-password')
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function sendResetEmail(Request $request) {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = strtolower(trim($data['email']));

        // Always the same response for known/unknown/soft-deleted accounts.
        // Broker applies token lifetime and per-email throttle.
        try {
            Password::broker()->sendResetLink(['email' => $email]);
        } catch (\Throwable $e) {
            // Never write reset tokens, mail content, or user-provided addresses to application logs.
            Log::error('Pengiriman tautan reset akun gagal.', ['exception_class' => get_class($e)]);
        }

        return back()->with('success',
            'Jika email terdaftar dan aktif, tautan reset akan dikirim. Periksa Inbox atau Spam.');
    }

    public function resetForm(Request $request, string $token) {
        return response()->view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')])
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function reset(Request $request) {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()]
        ]);
        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            // Revoke existing database sessions when the password is reset.
            if (config('session.driver') === 'database') {
                DB::table('sessions')->where('user_id', $user->getKey())->delete();
            }
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password berhasil diperbarui. Silakan login.')
            : back()->withErrors(['email' => __($status)]);
    }
}
