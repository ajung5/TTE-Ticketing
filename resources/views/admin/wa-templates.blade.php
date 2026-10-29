@extends('layouts.app')
@section('title', 'Preset WhatsApp')
@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">ADMIN / PENGATURAN</div>
            <h1>Preset Pesan WhatsApp</h1>
            <p class="muted">Enam jenis notifikasi untuk alur pemeriksaan AMS BSrE dan penyelesaian tiket.</p>
        </div>
        <span class="pill pill-blue">6 jenis notifikasi</span>
    </div>
    <div class="note-box notice-blue">
        <strong>Pengiriman dilakukan secara manual oleh verifikator.</strong>
        <p class="small muted">Preset untuk Reset, Pembaruan, dan Pendaftaran dipisahkan. Tautan survei internal otomatis
            ditambahkan ke notifikasi Selesai. Jangan meminta OTP, password, atau passphrase.</p>
    </div>
    <div class="columns template-columns">
        <section class="stack">
            @foreach (\App\Models\WaMessageTemplate::EVENTS as $eventCode => $eventLabel)
                @php($template = $templates->get($eventCode))
                <div class="card template-card">
                    <div class="section-heading">
                        <div>
                            <div class="eyebrow">NOTIFIKASI {{ $loop->iteration }}</div>
                            <h2>{{ $eventLabel }}</h2>
                        </div>
                        <span class="pill {{ $eventCode === 'completed' ? 'pill-green' : 'pill-blue' }}">
                            {{ \App\Models\WaMessageTemplate::TEMPLATE_SERVICE_CODES[$eventCode] === '*' ? 'Semua Layanan' : \App\Models\WaMessageTemplate::TEMPLATE_SERVICE_CODES[$eventCode] }}
                        </span>
                    </div>
                    @if ($template)
                        <form method="post" action="{{ route('admin.templates.update', $template) }}" class="stack">
                            @csrf @method('PUT')
                            <label for="body-{{ $eventCode }}">Isi pesan WhatsApp</label>
                            <textarea id="body-{{ $eventCode }}" name="body" rows="9" minlength="40" maxlength="3500" required
                                class="template-textarea">{{ old('body', $template->body) }}</textarea>
                            <div class="template-actions">
                                <span class="small muted">Terakhir diperbarui
                                    {{ $template->updated_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</span>
                                <button class="btn btn-primary" type="submit">Simpan Preset</button>
                            </div>
                        </form>
                    @else
                        <div class="alert error">Preset belum tersedia. Jalankan <code>php artisan migrate</code>.</div>
                    @endif
                </div>
            @endforeach
        </section>
        <aside class="stack">
            <section class="card sticky-guide">
                <div class="eyebrow">PANDUAN PENULISAN</div>
                <h2>Placeholder Dinamis</h2>
                <p class="small muted">Klik token setelah memilih textarea tujuan. Token NIK tetap tersamarkan di pesan
                    WhatsApp.</p>
                <div class="token-list">
                    @foreach (\App\Models\WaMessageTemplate::VARIABLE_DESCRIPTIONS as $token => $desc)
                        <div class="token-row">
                            <button type="button" class="token-insert" data-insert-token="{{ $token }}"
                                title="Masukkan token ke editor">{{ $token }}</button>
                            <span class="small muted">{{ $desc }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="note-box"><strong>Survei internal</strong>
                    <p class="small muted">Gunakan <code>{survey_url}</code> pada preset Selesai. Tautan berlaku 30 hari,
                        dan satu tiket hanya boleh mengisi satu survei.</p>
                </div>
                <div class="note-box"><strong>Token wajib</strong>
                    <p class="small muted"><code>{nama_pemohon}</code>, <code>{nomor_tiket}</code>,
                        <code>{nama_verifikator}</code>.</p>
                </div>
            </section>
        </aside>
    </div>
@endsection
