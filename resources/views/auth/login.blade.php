@extends ('layouts.app')
@section ('title', 'Masuk')
@section ('content')
    <div class="auth-card">
        <div class="auth-logo">T</div>
        <h1>Selamat datang</h1>
        <p class="muted">Masuk untuk mengelola permohonan sertifikat elektronik.</p>
        <form method="post" action="{{ route('login.submit') }}" class="stack mt-lg" autocomplete="off">
            @csrf
            <div class="form-field">
                <label for="login-email">Email akun</label>
                <input
                    id="login-email"
                    name="email"
                    type="email"
                    autocomplete="off"
                    required
                    autofocus />
            </div>
            <div class="form-field">
                <label for="login-password">Password</label>
                <input
                    id="login-password"
                    name="password"
                    type="password"
                    autocomplete="off"
                    required />
            </div>
            @if ($captchaReady ?? false)
                <div class="login-captcha">
                    <div class="cf-turnstile" data-sitekey="{{ config('tte.turnstile.site_key') }}" data-action="tte_login" data-size="flexible"></div>
                    @error ('cf-turnstile-response')
                        <p class="small" role="alert">{{ $message }}</p>
                    @enderror
                    <noscript><p class="small">Aktifkan JavaScript untuk menyelesaikan CAPTCHA.</p></noscript>
                </div>
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            @else
                <div class="alert warn" role="alert">Verifikasi CAPTCHA belum dikonfigurasi. Hubungi administrator sistem.</div>
            @endif
            <button class="btn btn-primary full" type="submit" @disabled (!($captchaReady ?? false))>Masuk ke Sistem</button>
            <p class="small muted center">Jangan simpan password pada browser di perangkat bersama. Selalu keluar dari sistem setelah selesai.</p>
        </form>
        <p class="muted small center mt-lg"><a href="{{ route('password.request') }}">Lupa Password?</a></p>
    </div>
@endsection
