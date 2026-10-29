@extends('layouts.app')
@section('title', 'Reset Password')
@section('content')
    <div class="auth-card">
        <div class="auth-logo">T</div>
        <h1>Buat Password Baru</h1>
        <p class="muted">Gunakan minimal 12 karakter dengan huruf besar, huruf kecil, dan angka.</p>
        <form method="post" action="{{ route('password.update') }}" class="stack mt-lg">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label>Email<input type="email" name="email" value="{{ old('email', $email) }}" required></label>
            <label>Password baru<input type="password" name="password" autocomplete="new-password" required></label>
            <label>Konfirmasi password<input type="password" name="password_confirmation" autocomplete="new-password"
                    required></label>
            <button class="btn btn-primary full" type="submit">Simpan Password Baru</button>
        </form>
        <div class="center mt"><a href="{{ route('login') }}">Kembali ke Login</a></div>
    </div>
@endsection
