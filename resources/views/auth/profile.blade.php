@extends ('layouts.app')

@section ('title', 'Profil Akun')

@section ('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">AKUN SAYA</div>
            <h1>Profil &amp; Password</h1>
            <p class="muted">Kelola informasi profil dan keamanan akun Anda.</p>
        </div>
    </div>

    <div class="columns">
        {{-- INFORMASI PROFIL --}}
        <section class="card">
            <h2>Informasi Profil</h2>

            <form method="POST" action="{{ route('profile.update') }}" class="stack">
                @csrf
                @method ('PATCH')

                <div class="form-field">
                    <label for="profile-name">Nama Lengkap</label>
                    <input
                        id="profile-name"
                        type="text"
                        name="name"
                        value="{{ old('name', $user->name) }}"
                        minlength="3"
                        maxlength="255"
                        autocomplete="name"
                        required />
                </div>

                <div class="form-field">
                    <label for="profile-email">Email</label>
                    <input
                        id="profile-email"
                        type="email"
                        name="email"
                        value="{{ old('email', $user->email) }}"
                        maxlength="255"
                        autocomplete="email"
                        required />
                </div>

                <div class="form-field">
                    <label for="profile-initials">Inisial</label>
                    <input
                        id="profile-initials"
                        type="text"
                        name="initials"
                        value="{{ old('initials', $user->initials) }}"
                        maxlength="12"
                        placeholder="Contoh: Aj. 01 / TTE"
                        autocomplete="off" />
                </div>

                <div class="form-field">
                    <label for="profile-role">Peran</label>
                    <input
                        id="profile-role"
                        type="text"
                        value="{{ $user->isAdmin() ? 'Administrator' : 'Verifikator' }}"
                        readonly
                        aria-readonly="true" />
                </div>

                <div class="form-field">
                    <label for="profile-current-password">Password Saat Ini</label>
                    <input
                        id="profile-current-password"
                        type="password"
                        name="current_password"
                        autocomplete="current-password"
                        placeholder="Wajib jika mengubah email" />
                </div>

                <p class="small muted">Nama lengkap dan inisial dapat diubah langsung. Inisial bebas maksimal 12 karakter (tanpa baris baru) dan dapat digunakan pada tanda tangan preset WhatsApp. Untuk mengubah email login, masukkan password saat ini. Gunakan email baru saat login berikutnya.</p>

                <button type="submit" class="btn btn-primary">Simpan Perubahan Profil</button>
            </form>
        </section>

        {{-- GANTI PASSWORD --}}
        <section class="card">
            <h2>Ganti Password Saya</h2>

            <form method="POST" class="stack" action="{{ route('profile.password') }}">
                @csrf

                <div class="form-field">
                    <label for="current_password">Password Saat Ini</label>
                    <input
                        id="current_password"
                        type="password"
                        name="current_password"
                        autocomplete="current-password"
                        required />
                </div>

                <div class="form-field">
                    <label for="new_password">Password Baru</label>
                    <input
                        id="new_password"
                        type="password"
                        name="password"
                        minlength="12"
                        autocomplete="new-password"
                        required />
                </div>

                <div class="form-field">
                    <label for="password_confirmation">Konfirmasi Password Baru</label>
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        minlength="12"
                        autocomplete="new-password"
                        required />
                </div>

                <p class="small muted">Minimal 12 karakter, menggunakan huruf besar, huruf kecil, dan angka.</p>

                <button class="btn btn-primary" type="submit">Perbarui Password</button>
            </form>
        </section>
    </div>
@endsection
