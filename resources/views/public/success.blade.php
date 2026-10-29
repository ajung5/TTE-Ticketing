@extends('layouts.app')
@section('title', 'Permohonan Terkirim')
@section('content')
<div class="public-success card">
    <div class="public-success-icon">✓</div>
    <div class="eyebrow">PERMOHONAN BERHASIL DIKIRIM</div>
    <h1>Terima kasih, permohonan Anda tercatat.</h1>
    <p class="muted">Simpan nomor tiket berikut sebagai referensi saat menghubungi petugas layanan TTE.</p>
    <div class="public-ticket-number">{{ $ticketNumber }}</div>
    <div class="note-box"><strong>Langkah selanjutnya</strong><p class="small muted">Permohonan akan ditangani oleh verifikator. Pastikan nomor WhatsApp aktif dan telah terverifikasi di AMS BSrE. Petugas tidak pernah meminta OTP, password, atau passphrase.</p></div>
    <a href="{{ route('public.form') }}" class="btn btn-primary full">Kembali ke Formulir</a>
</div>
@endsection
