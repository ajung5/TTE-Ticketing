@extends ('layouts.app')
@section ('title', 'Permohonan Terkirim')
@section ('content')
    <div class="public-success card {{ $activeRequestNotice ? 'public-success--blocked' : '' }}">
        @if ($activeRequestNotice)
            <div
                class="tte-request-blocked tte-request-blocked--danger"
                role="alert"
                aria-labelledby="tte-request-blocked-heading">
                <div class="tte-request-blocked__icon" aria-hidden="true">!</div>
                <div class="tte-request-blocked__body">
                    <div class="tte-request-blocked__eyebrow">
                        PERMOHONAN SEBELUMNYA MASIH AKTIF
                    </div>
                    <h1 id="tte-request-blocked-heading">
                        Silakan tindak lanjuti permohonan sebelumnya.
                    </h1>
                    <p>Sistem tidak membuat tiket baru karena masih terdapat permohonan TTE yang belum selesai. Silakan cek pesan WhatsApp dari petugas atau hubungi layanan TTE melalui kanal resmi jika belum menerima informasi tindak lanjut.</p>
                </div>
            </div>
            <div class="note-box tte-request-blocked__advice">
                <strong>Langkah yang perlu dilakukan</strong>
                <p class="small">Jika petugas telah meminta aktivasi akun, verifikasi WhatsApp, set passphrase, atau tindakan lainnya, lanjutkan pada tiket yang sama. Jangan memberikan OTP, password, atau passphrase kepada siapa pun.</p>
            </div>
            <a href="{{ route('public.form') }}" class="btn btn-outline full"
                >Kembali ke Formulir</a>
        @else
            <div class="public-success-icon">✓</div>
            <div class="eyebrow">PERMOHONAN BERHASIL DIKIRIM</div>
            <h1>Terima kasih, permohonan Anda tercatat.</h1>
            <p class="muted">Simpan nomor tiket berikut sebagai referensi saat menghubungi petugas layanan TTE.</p>
            <div class="public-ticket-number" id="tte-ux-ticket-number">{{ $ticketNumber }}</div>
            <div class="tte-ux-copy-controls">
                <button type="button" class="btn btn-outline" data-tte-ticket-copy>
                    Salin Nomor Tiket
                </button>
                <span id="tte-ux-copy-feedback" class="tte-ux-copy-feedback" role="status" aria-live="polite"></span>
            </div>
            <div class="note-box">
                <strong>Langkah selanjutnya</strong>
                <p class="small muted">Permohonan akan ditangani oleh verifikator. Pastikan nomor WhatsApp aktif dan telah terverifikasi di AMS BSrE. Petugas tidak pernah meminta OTP, password, atau passphrase.</p>
            </div>
            <a href="{{ route('public.form') }}" class="btn btn-primary full"
                >Kembali ke Formulir</a>
        @endif
    </div>
    @if (!$activeRequestNotice)
        <script src="{{ asset('js/tte-request.js') }}?v=617" defer></script>
    @endif
@endsection
