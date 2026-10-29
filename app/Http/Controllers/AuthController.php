<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

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
        abort_unless(
            $request->user()?->isAdmin(),
            403,
            'Hanya administrator yang dapat mengubah nama profil melalui halaman ini.'
        );

        $data = $request->validate(
            [
                'name' => ['required', 'string', 'min:3', 'max:255']
            ],
            [
                'name.required' => 'Nama administrator wajib diisi.',
                'name.min' => 'Nama minimal 3 karakter.',
                'name.max' => 'Nama maksimal 255 karakter.'
            ]
        );

        $request->user()->update([
            'name' => trim($data['name'])
        ]);

        return redirect()->route('profile')->with('success', 'Nama profil administrator berhasil diperbarui.');
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

    public function resetForm(Request $request, string $token) {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function reset(Request $request) {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()]
        ]);
        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password berhasil diperbarui. Silakan login.')
            : back()->withErrors(['email' => __($status)]);
    }
}
