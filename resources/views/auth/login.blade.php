@extends('layouts.app')
@section('title', 'Masuk')
@section('content')
<div class="auth-card">
    <div class="auth-logo">T</div>
    <h1>Selamat datang</h1>
    <p class="muted">Masuk untuk mengelola permohonan sertifikat elektronik.</p>
    <form method="post" action="{{ route('login.submit') }}" class="stack mt-lg">
        @csrf
        <label>Email akun<input name="email" type="email" autocomplete="username" value="{{ old('email') }}" required autofocus></label>
        <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
        <button class="btn btn-primary full" type="submit">Masuk ke Sistem</button>
    </form>
    <p class="muted small center mt-lg">Jika lupa password, hubungi administrator layanan untuk meminta tautan reset.</p>
</div>
@endsection
