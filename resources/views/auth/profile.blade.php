@extends('layouts.app')

@section('title', 'Profil Akun')

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">AKUN SAYA</div>
            <h1>Profil &amp; Password</h1>
            <p class="muted">
                Kelola informasi profil dan keamanan akun Anda.
            </p>
        </div>
    </div>

    <div class="columns">

        {{-- INFORMASI PROFIL --}}
        <section class="card">
            <h2>Informasi Profil</h2>

            @if ($user->isAdmin())
                <form method="POST" action="{{ route('profile.update') }}" class="stack">

                    @csrf
                    @method('PATCH')

                    <label for="profile-name">
                        Nama Lengkap
                        <input id="profile-name" type="text" name="name" value="{{ old('name', $user->name) }}"
                            minlength="3" maxlength="255" autocomplete="name" required>
                    </label>

                    <label for="profile-email">
                        Email
                        <input id="profile-email" type="email" name="email" value="{{ old('email', $user->email) }}"
                            maxlength="255" autocomplete="email" required>
                    </label>

                    <label for="profile-initials">
                        Inisial
                        <input id="profile-initials" type="text" name="initials"
                            value="{{ old('initials', $user->initials) }}" maxlength="12" placeholder="Contoh: ADM"
                            autocomplete="off">
                    </label>

                    <label for="profile-role">
                        Peran
                        <input id="profile-role" type="text" value="Administrator" readonly aria-readonly="true">
                    </label>

                    <label for="profile-current-password">
                        Password Saat Ini
                        <input id="profile-current-password" type="password" name="current_password"
                            autocomplete="current-password" placeholder="Wajib jika mengubah email">
                    </label>

                    <p class="small muted">
                        Nama lengkap dan inisial dapat diubah langsung.
                        Jika mengubah alamat email, masukkan password
                        saat ini sebagai konfirmasi keamanan.
                        Gunakan email baru saat login berikutnya.
                    </p>

                    <button type="submit" class="btn btn-primary">
                        Simpan Perubahan Profil
                    </button>
                </form>
            @else
                <div class="detail-grid">
                    <div>
                        <div class="field-name">Nama</div>
                        <div class="field-value">
                            {{ $user->name }}
                        </div>
                    </div>

                    <div>
                        <div class="field-name">Email</div>
                        <div class="field-value">
                            {{ $user->email }}
                        </div>
                    </div>

                    <div>
                        <div class="field-name">Inisial</div>
                        <div class="field-value">
                            {{ $user->initials ?: '-' }}
                        </div>
                    </div>

                    <div>
                        <div class="field-name">Peran</div>
                        <div class="field-value">
                            Verifikator
                        </div>
                    </div>
                </div>
            @endif
        </section>

        {{-- GANTI PASSWORD --}}
        <section class="card">
            <h2>Ganti Password Saya</h2>

            <form method="POST" class="stack" action="{{ route('profile.password') }}">
                @csrf

                <label for="current_password">
                    Password Saat Ini
                    <input id="current_password" type="password" name="current_password" autocomplete="current-password"
                        required>
                </label>

                <label for="new_password">
                    Password Baru
                    <input id="new_password" type="password" name="password" minlength="12" autocomplete="new-password"
                        required>
                </label>

                <label for="password_confirmation">
                    Konfirmasi Password Baru
                    <input id="password_confirmation" type="password" name="password_confirmation" minlength="12"
                        autocomplete="new-password" required>
                </label>

                <p class="small muted">
                    Minimal 12 karakter, menggunakan huruf
                    besar, huruf kecil, dan angka.
                </p>

                <button class="btn btn-primary" type="submit">
                    Perbarui Password
                </button>
            </form>
        </section>

    </div>
@endsection
