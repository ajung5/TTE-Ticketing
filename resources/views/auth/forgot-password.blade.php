@extends ('layouts.app')
@section ('title', 'Lupa Password')
@section ('content')
    <div class="auth-card">
        <div class="auth-logo">T</div>
        <h1>Lupa Password?</h1>
        <p class="muted">Masukkan email akun admin atau verifikator. Jika terdaftar, tautan reset password akan dikirim ke alamat tersebut.</p>
        <form method="post" action="{{ route('password.email') }}" class="stack mt-lg">
            @csrf
            <label for="reset-email"
                >Email akun
                <input
                    id="reset-email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    value="{{ old('email') }}"
                    maxlength="255"
                    required
                    autofocus
                    placeholder="nama@subang.go.id" />
            </label>
            <button class="btn btn-primary full" type="submit">Kirim Tautan Reset Password</button>
        </form>
        <p class="small muted center mt">Periksa Inbox dan folder Spam. Tautan hanya berlaku selama 60 menit.</p>
        <div class="center mt"><a href="{{ route('login') }}">Kembali ke Login</a></div>
    </div>
@endsection
