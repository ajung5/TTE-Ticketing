@extends('layouts.app')
@section('title', 'Permohonan Layanan TTE')
@section('content')
<div class="public-request-wrap">
    <header class="public-header">
        <div class="brand-icon">T</div>
        <div><div class="eyebrow">PEMERINTAH KABUPATEN SUBANG</div><h1>Permohonan Layanan TTE</h1><p class="muted">Pengajuan sertifikat elektronik BSrE tanpa perlu login.</p></div>
    </header>
    <div class="public-layout">
        <section class="card public-form-card">
            <div class="section-heading"><div><h2>Formulir Permohonan</h2><p class="small muted">Kolom bertanda * wajib diisi. Pastikan data sesuai identitas resmi.</p></div><span class="pill pill-blue">Layanan Online</span></div>
            <form method="post" action="{{ route('public.submit') }}" class="stack">
                @csrf
                <input type="hidden" name="submission_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <div class="honeypot" aria-hidden="true"><label for="website">Biarkan kosong</label><input id="website" name="website" tabindex="-1" autocomplete="off" value=""></div>
                <label for="layanan">Jenis Layanan *
                    <select id="layanan" name="layanan" required>
                        <option value="">Pilih jenis permohonan</option>
                        @foreach(\App\Models\WaMessageTemplate::SERVICES as $code => $label)
                            <option value="{{ $code }}" @selected(old('layanan') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="public-fields">
                    <label for="nama_user">Nama Lengkap *<input id="nama_user" name="nama_user" maxlength="160" required value="{{ old('nama_user') }}" autocomplete="name" placeholder="Nama lengkap pemohon"></label>
                    <label for="nik">NIK (16 digit) *<input id="nik" name="nik" type="text" inputmode="numeric" pattern="[0-9]{16}" minlength="16" maxlength="16" required autocomplete="off" placeholder="16 digit NIK"></label>
                    <label for="nip">NIP (jika ada)<input id="nip" name="nip" maxlength="32" value="{{ old('nip') }}" autocomplete="off" placeholder="NIP pegawai"></label>
                    <label for="email">Email Dinas *<input id="email" name="email" type="email" maxlength="180" required value="{{ old('email') }}" placeholder="nama@subang.go.id" autocomplete="email"></label>
                    <label for="no_wa">Nomor WhatsApp *<input id="no_wa" name="no_wa" type="tel" maxlength="30" required value="{{ old('no_wa') }}" placeholder="081234567890" autocomplete="tel"></label>
                    <label for="opd">Unit Kerja / OPD *<input id="opd" name="opd" maxlength="180" required value="{{ old('opd') }}" placeholder="Nama perangkat daerah"></label>
                    <label for="jabatan">Jabatan *<input id="jabatan" name="jabatan" maxlength="180" required value="{{ old('jabatan') }}" placeholder="Jabatan saat ini"></label>
                </div>
                <label for="keterangan">Keterangan Tambahan (opsional)<textarea name="keterangan" id="keterangan" rows="3" maxlength="2000" placeholder="Jelaskan kebutuhan atau kendala secara singkat. Jangan masukkan OTP, password, atau passphrase.">{{ old('keterangan') }}</textarea></label>
                <label class="public-check"><input type="checkbox" name="persetujuan" value="1" required @checked(old('persetujuan'))><span>Saya menyetujui pemrosesan data identitas dan kontak yang diperlukan untuk layanan TTE serta pemberitahuan status melalui WhatsApp. *</span></label>
                <div class="note-box"><strong>Keamanan informasi</strong><p class="small muted">Jangan memasukkan passphrase, password AMS, OTP, atau mengunggah KTP. NIK disimpan secara terenkripsi dan ditampilkan tersamarkan pada pesan WhatsApp.</p></div>
                <button class="btn btn-primary full" type="submit">Kirim Permohonan TTE →</button>
            </form>
        </section>
        <aside class="stack public-aside">
            <section class="card"><div class="eyebrow">ALUR LAYANAN</div><h2>Setelah Formulir Dikirim</h2><ol class="public-steps"><li>Permohonan menerima nomor tiket otomatis.</li><li>Verifikator mengambil tiket dan memeriksa data.</li><li>Petugas memeriksa status nomor WA di AMS BSrE.</li><li>Petugas menyampaikan tindak lanjut atau instruksi verifikasi nomor.</li></ol></section>
            <section class="card"><h2>Sudah menjadi petugas?</h2><p class="small muted">Halaman login hanya untuk admin dan verifikator.</p><a href="{{ route('login') }}" class="btn btn-outline full">Login Petugas</a></section>
        </aside>
    </div>
</div>
@endsection
