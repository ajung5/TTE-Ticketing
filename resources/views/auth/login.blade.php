@extends ('layouts.app')
@section ('title', 'Masuk')
@section ('content')
    <div class="auth-card">
        <div class="auth-logo">T</div>
        <h1>Selamat datang</h1>
        <p class="muted">Masuk untuk mengelola permohonan sertifikat elektronik.</p>
        <form method="post" action="{{ route('login.submit') }}" class="stack mt-lg">
            @csrf
            <div class="form-field">
                <label for="login-email">Email akun</label>
                <input
                    id="login-email"
                    name="email"
                    type="email"
                    autocomplete="username"
                    value="{{ old('email') }}"
                    required
                    autofocus />
            </div>
            <div class="form-field">
                <label for="login-password">Password</label>
                <input
                    id="login-password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required />
            </div>
            <button class="btn btn-primary full" type="submit">Masuk ke Sistem</button>
        </form>
        <p class="muted small center mt-lg"><a href="{{ route('password.request') }}">Lupa Password?</a></p>
    </div>
@endsection
