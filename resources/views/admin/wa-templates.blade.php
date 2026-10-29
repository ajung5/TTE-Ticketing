@extends('layouts.app')
@section('title', 'Preset WhatsApp')
@section('content')
@php
    $services = \App\Models\WaMessageTemplate::SERVICES;
    $events = \App\Models\WaMessageTemplate::EVENTS;
    $selected = array_key_exists((string) request('service'), $services) ? request('service') : 'Reset Passphrase';
@endphp
<div class="page-heading"><div><div class="eyebrow">ADMIN / PENGATURAN</div><h1>Preset Pesan WhatsApp</h1><p class="muted">Kelola redaksi notifikasi untuk setiap layanan dan tahap penanganan.</p></div><span class="pill pill-blue">6 preset aktif</span></div>
<div class="note-box notice-blue"><strong>Pesan tetap dikirim secara manual.</strong><p class="small muted">Perubahan preset berlaku saat pesan dibuat berikutnya. Pesan yang sebelumnya tercatat di log tidak diubah. Jangan meminta passphrase, OTP, atau password melalui WhatsApp.</p></div>
<div class="service-tabs" role="navigation" aria-label="Pilih jenis layanan">
    @foreach($services as $code => $label)
        <a class="service-tab {{ $selected === $code ? 'active' : '' }}" href="{{ route('admin.templates.index', ['service' => $code]) }}">{{ $label }}</a>
    @endforeach
</div>
<div class="columns template-columns">
    <section class="stack">
    @foreach($events as $eventCode => $eventLabel)
        @php($template = $templates->get($selected.'|'.$eventCode))
        <div class="card template-card">
            <div class="section-heading"><div><div class="eyebrow">{{ $selected === 'Reset Passphrase' ? 'RESET PASSPHRASE' : ($selected === 'Pembaruan' ? 'RENEWAL' : 'PENDAFTARAN BARU') }}</div><h2>{{ $eventLabel === 'Selesai' ? 'Pesan Setelah Selesai' : 'Pesan Tindak Lanjut' }}</h2></div><span class="pill {{ $eventCode === 'completed' ? 'pill-green' : 'pill-blue' }}">{{ $eventLabel }}</span></div>
            @if($template)
                <form method="post" action="{{ route('admin.templates.update', $template) }}" class="stack">
                    @csrf @method('PUT')
                    <label for="body-{{ $eventCode }}">Isi pesan WhatsApp</label>
                    <textarea id="body-{{ $eventCode }}" name="body" rows="12" minlength="40" maxlength="2500" required class="template-textarea">{{ old('body', $template->body) }}</textarea>
                    <div class="template-actions"><span class="small muted">Terakhir diperbarui {{ $template->updated_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span><button class="btn btn-primary" type="submit">Simpan Preset</button></div>
                </form>
            @else
                <div class="alert error">Preset belum tersedia. Jalankan migrasi database terbaru.</div>
            @endif
        </div>
    @endforeach
    </section>
    <aside class="stack">
        <section class="card sticky-guide"><div class="eyebrow">PANDUAN PENULISAN</div><h2>Placeholder Dinamis</h2><p class="small muted">Variabel berikut otomatis diganti berdasarkan data tiket ketika pesan dibuat.</p>
            <div class="token-list">
            @foreach(['{nama_pemohon}' => 'Nama pemohon','{nomor_tiket}' => 'Nomor tiket','{layanan}' => 'Jenis layanan','{nama_verifikator}' => 'Nama petugas','{inisial_verifikator}' => 'Inisial petugas','{nama_instansi}' => 'Nama layanan/instansi','{opd}' => 'Unit kerja pemohon'] as $token => $desc)
                <div class="token-row"><code>{{ $token }}</code><span class="small muted">{{ $desc }}</span></div>
            @endforeach
            </div>
            <div class="note-box"><strong>Variabel wajib</strong><p class="small muted"><code>{nama_pemohon}</code>, <code>{nomor_tiket}</code>, dan <code>{nama_verifikator}</code> harus ada di setiap preset.</p></div>
        </section>
    </aside>
</div>
@endsection
