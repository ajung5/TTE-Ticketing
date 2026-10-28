@extends('layouts.app')
@section('title', 'Profil Akun')
@section('content')
<div class="page-heading"><div><div class="eyebrow">AKUN SAYA</div><h1>Profil &amp; Password</h1><p class="muted">Kelola kredensial pribadi tanpa mengubah tugas atau peran.</p></div></div>
<div class="columns">
    <section class="card"><h2>Informasi Akun</h2><div class="detail-grid">
        <div><div class="field-name">Nama</div><div class="field-value">{{ $user->name }}</div></div>
        <div><div class="field-name">Email</div><div class="field-value">{{ $user->email }}</div></div>
        <div><div class="field-name">Inisial</div><div class="field-value">{{ $user->initials ?: '-' }}</div></div>
        <div><div class="field-name">Peran</div><div class="field-value">{{ $user->isAdmin() ? 'Admin' : 'Verifikator' }}</div></div>
    </div></section>
    <section class="card"><h2>Ganti Password Saya</h2>
        <form method="post" class="stack" action="{{ route('profile.password') }}">@csrf
            <label>Password saat ini<input type="password" name="current_password" autocomplete="current-password" required></label>
            <label>Password baru<input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
            <label>Konfirmasi password baru<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
            <p class="small muted">Minimal 12 karakter, huruf besar, huruf kecil, dan angka.</p>
            <button class="btn btn-primary" type="submit">Perbarui Password</button>
        </form>
    </section>
</div>
@endsection
