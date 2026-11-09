@extends('layouts.app')
@section('title', 'Permohonan Terkirim')
@section('content')
    <div class="public-success card">
        @if ($activeRequestNotice)
            <div class="public-success-icon" aria-hidden="true">!</div>
            <div class="eyebrow">PERMOHONAN SEBELUMNYA MASIH AKTIF</div>
            <h1>Silakan tindak lanjuti permohonan sebelumnya.</h1>
            <p class="muted">Sistem tidak membuat tiket baru karena masih terdapat permohonan TTE
                yang belum selesai. Silakan cek pesan WhatsApp dari petugas atau hubungi layanan TTE
                melalui kanal resmi jika belum menerima informasi tindak lanjut.</p>
            <div class="note-box"><strong>Perhatian</strong>
                <p class="small muted">Jika petugas telah meminta aktivasi akun, verifikasi WhatsApp,
                    set passphrase, atau tindakan lainnya, lanjutkan pada tiket yang sama.
                    Jangan memberikan OTP, password, atau passphrase kepada siapa pun.</p>
            </div>
            <a href="{{ route('public.form') }}" class="btn btn-outline full">Kembali ke Formulir</a>
        @else
        <div class="public-success-icon">✓</div>
        <div class="eyebrow">PERMOHONAN BERHASIL DIKIRIM</div>
        <h1>Terima kasih, permohonan Anda tercatat.</h1>
        <p class="muted">Simpan nomor tiket berikut sebagai referensi saat menghubungi petugas layanan TTE.</p>
        <div class="public-ticket-number">{{ $ticketNumber }}</div>
        <div class="note-box"><strong>Langkah selanjutnya</strong>
            <p class="small muted">Permohonan akan ditangani oleh verifikator. Pastikan nomor WhatsApp aktif dan telah
                terverifikasi di AMS BSrE. Petugas tidak pernah meminta OTP, password, atau passphrase.</p>
        </div>
        <a href="{{ route('public.form') }}" class="btn btn-primary full">Kembali ke Formulir</a>
        @endif
    </div>
@endsection
