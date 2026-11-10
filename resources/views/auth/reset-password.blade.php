@extends ('layouts.app')
@section ('title', 'Reset Password')
@section ('content')
    <div class="auth-card">
        <div class="auth-logo">T</div>
        <h1>Buat Password Baru</h1>
        <p class="muted">Gunakan minimal 12 karakter dengan huruf besar, huruf kecil, dan angka.</p>
        <form method="post" action="{{ route('password.update') }}" class="stack mt-lg">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}" />
            <div class="form-field">
                <label for="reset-password-email">Email</label>
                <input
                    id="reset-password-email"
                    type="email"
                    name="email"
                    value="{{ old('email', $email) }}"
                    required />
            </div>
            <div class="form-field">
                <label for="reset-password-password">Password baru</label>
                <input
                    id="reset-password-password"
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    required />
            </div>
            <div class="form-field">
                <label for="reset-password-password-confirmation">Konfirmasi password</label>
                <input
                    id="reset-password-password-confirmation"
                    type="password"
                    name="password_confirmation"
                    autocomplete="new-password"
                    required />
            </div>
            <button class="btn btn-primary full" type="submit">Simpan Password Baru</button>
        </form>
        <div class="center mt"><a href="{{ route('login') }}">Kembali ke Login</a></div>
    </div>
@endsection
