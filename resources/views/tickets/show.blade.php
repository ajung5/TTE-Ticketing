@extends('layouts.app')
@section('title', 'Detail Tiket')
@section('content')
<div class="page-heading"><div><a href="{{ route('tickets.index') }}" class="breadcrumb-link">← Kembali ke antrean</a><div class="eyebrow mt">DETAIL PERMOHONAN</div><h1>{{ $ticket->ticket_number ?? '#'.$ticket->id }}</h1><p class="muted">{{ $ticket->service_label }}</p></div><div class="heading-right"><span class="pill {{ $ticket->status === 'Done' ? 'pill-green' : ($ticket->status === 'Open' ? 'pill-neutral' : 'pill-blue') }}">{{ $ticket->status_label }}</span>@if($ticket->source === 'simulated')<span class="demo-label">DATA SIMULASI</span>@endif</div></div>
@if($ticket->source === 'simulated')
    <div class="alert warn" role="status"><strong>Mode simulasi:</strong> Tiket ini menggunakan data dan nomor WhatsApp fiktif. Tombol kirim ke WhatsApp dinonaktifkan; gunakan tombol Salin Pesan untuk menguji format pesan.</div>
@endif
<div class="columns detail-columns">
    <div class="stack">
        <section class="card"><div class="section-heading"><div><div class="eyebrow">INFORMASI PEMOHON</div><h2>Detail Permohonan</h2></div><span class="pill pill-neutral">{{ strtoupper($ticket->source) }}</span></div>
            <div class="detail-grid">
                <div><div class="field-name">Nama Pemohon</div><div class="field-value">{{ $ticket->nama_user }}</div></div>
                <div><div class="field-name">Nomor WhatsApp</div><div class="field-value">{{ $ticket->no_wa }}</div></div>
                <div><div class="field-name">NIK</div><div class="field-value">{{ $ticket->masked_nik }} <span class="small muted">(tersamarkan)</span></div></div>
                <div><div class="field-name">NIP</div><div class="field-value">{{ $ticket->nip ?: '-' }}</div></div>
                <div><div class="field-name">Unit Kerja / OPD</div><div class="field-value">{{ $ticket->opd ?: '-' }}</div></div>
                <div><div class="field-name">Jabatan</div><div class="field-value">{{ $ticket->jabatan ?: '-' }}</div></div>
                <div><div class="field-name">Email Dinas</div><div class="field-value">{{ $ticket->email ?: '-' }}</div></div>
                <div><div class="field-name">Tanggal Masuk</div><div class="field-value">{{ $ticket->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</div></div>
                <div><div class="field-name">Penanggung Jawab</div><div class="field-value">{{ $ticket->claimant?->name ?? 'Belum diambil' }}</div></div>
            </div>
            @if($ticket->signature_path && $ticket->claimed_by === auth()->id())
                <div class="note-box mt"><div class="field-name">Tanda tangan persetujuan pemohon</div>
                    <div class="small muted">Persetujuan tercatat: {{ $ticket->consent_accepted_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') ?? '-' }}</div>
                    <img src="{{ route('tickets.signature', $ticket) }}" alt="Tanda tangan persetujuan formulir pemohon" class="signature-evidence" loading="lazy">
                    <div class="small muted">Tanda tangan persetujuan bukan TTE tersertifikasi BSrE dan tidak membuktikan identitas secara mandiri.</div>
                </div>
            @endif
            @if($ticket->keterangan)<div class="note-box"><div class="field-name">Keterangan pemohon</div><div class="pre-wrap">{{ $ticket->keterangan }}</div></div>@endif
        </section>
        <section class="card"><div class="section-heading"><div><div class="eyebrow">JEJAK AKTIVITAS</div><h2>Riwayat Penanganan</h2></div><span class="pill pill-neutral">{{ $ticket->activities->count() }} aktivitas</span></div>
            <div class="timeline">
                @forelse($ticket->activities as $entry)
                    <div class="timeline-item"><div><strong>{{ match($entry->action) {'claimed'=>'Tiket diambil','released'=>'Tiket dilepas','note'=>'Catatan tindak lanjut','completed'=>'Tiket diselesaikan','wa_confirmed'=>'WhatsApp penyelesaian dikonfirmasi','wa_followup_confirmed'=>'WhatsApp tindak lanjut dikonfirmasi','wa_verified'=>'Nomor WA terverifikasi di AMS','wa_unverified'=>'Nomor WA belum terverifikasi di AMS','wa_unverified_confirmed'=>'Informasi WA belum terverifikasi dikonfirmasi',default=>$entry->action} }}</strong><div class="small muted">{{ $entry->actor?->name ?? 'Sistem' }} · {{ $entry->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</div></div>@if($entry->note)<p class="pre-wrap">{{ $entry->note }}</p>@endif</div>
                @empty<p class="muted">Belum ada aktivitas.</p>@endforelse
            </div>
        </section>
    </div>
    <div class="stack">
        <section class="card"><div class="section-heading"><div><div class="eyebrow">WORKFLOW</div><h2>Penanganan Tiket</h2></div><span class="stat-icon stat-indigo">▤</span></div>
            @if($ticket->status === 'Open')
                <p class="muted">Tiket belum diambil. Anda dapat mengambilnya untuk mulai menangani permohonan.</p>
                <form method="post" action="{{ route('tickets.claim', $ticket) }}">@csrf<button class="btn btn-primary full" type="submit">Ambil Tiket Ini →</button></form>
            @elseif($ticket->status === 'In Progress')
                <div class="note-box"><strong>Ditangani oleh {{ $ticket->claimant?->name }}</strong><p class="small muted">Setiap catatan akan tersimpan pada riwayat aktivitas.</p></div>
                <form class="stack mt" method="post" action="{{ route('tickets.note', $ticket) }}">@csrf<label>Catatan tindak lanjut<textarea name="note" rows="4" maxlength="3000" required placeholder="Contoh: dokumen diverifikasi, menunggu konfirmasi BSrE"></textarea></label><button type="submit" class="btn btn-outline full">Simpan Catatan</button></form>
                <hr>
                <form class="stack" method="post" action="{{ route('tickets.complete', $ticket) }}">@csrf<label>Hasil penyelesaian<textarea name="resolution_note" rows="4" minlength="10" maxlength="3000" required placeholder="Ringkasan hasil yang sudah dikonfirmasi"></textarea></label><p class="small muted">Selesaikan tiket hanya setelah hasil penanganan terverifikasi.</p><button type="submit" class="btn btn-primary full" @disabled($ticket->wa_verification_status !== 'verified')>Tandai Selesai ✓</button></form>
                @unless($ticket->activities->whereNotIn('action', ['claimed','released'])->count())
                    <form method="post" action="{{ route('tickets.release', $ticket) }}" class="mt">@csrf<button type="submit" class="btn btn-ghost full">Lepas Tiket (belum dikerjakan)</button></form>
                @endunless
            @else
                <div class="alert success">Tiket selesai pada {{ $ticket->completed_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }}.</div>
                <div class="field-name">Catatan hasil</div><p class="pre-wrap">{{ $ticket->resolution_note ?: 'Tiket lama: tidak ada catatan hasil.' }}</p>
            @endif
        </section>
        @if($ticket->status === 'In Progress' || ($ticket->status === 'Done' && $ticket->wa_verification_status !== 'verified'))
            <section class="card"><div class="section-heading"><div><div class="eyebrow">WAJIB SEBELUM KOMUNIKASI</div><h2>Verifikasi Nomor di AMS BSrE</h2></div></div>
                <div class="verification-panel">
                    <div class="verification-tag"><strong class="verification-status">{{ $ticket->wa_verification_label }}</strong>
                        @if($ticket->wa_checked_at)<div class="small muted">Diperiksa {{ $ticket->wa_checked_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</div>@endif
                    </div>
                    <p class="small muted">Periksa secara manual di portal resmi AMS BSrE. Nomor WhatsApp aktif tidak otomatis berarti terverifikasi pada AMS. Untuk tiket lama yang sudah selesai, pemeriksaan tetap diperlukan sebelum mengirim notifikasi.</p>
                    <form action="{{ route('tickets.verify-whatsapp', $ticket) }}" method="post" class="stack">@csrf
                        <label>Status pemeriksaan
                            <select name="wa_verification_status" required>
                                <option value="">Pilih hasil pemeriksaan</option>
                                <option value="verified" @selected(old('wa_verification_status', $ticket->wa_verification_status)==='verified')>Sudah terverifikasi di AMS BSrE</option>
                                @if($ticket->status === 'In Progress')<option value="unverified" @selected(old('wa_verification_status', $ticket->wa_verification_status)==='unverified')>Belum terverifikasi di AMS BSrE</option>@endif
                            </select>
                        </label>
                        <label>Catatan pemeriksaan <textarea name="wa_verification_note" minlength="8" maxlength="500" rows="2" required placeholder="Contoh: status nomor pada profil AMS belum terverifikasi. Jangan catat OTP atau password.">{{ old('wa_verification_note', $ticket->wa_verification_note) }}</textarea></label>
                        <button class="btn btn-primary full" type="submit">Simpan Hasil Pemeriksaan AMS</button>
                    </form>
                </div>
                @if($ticket->wa_verification_status !== 'verified')
                    <p class="small muted">Tindak lanjut biasa dan penyelesaian belum dapat dikirim. Jika status belum terverifikasi, gunakan pesan petunjuk verifikasi di bawah.</p>
                @endif
            </section>
        @endif
        @if($unverifiedMessage)
            @php($unverifiedNotification = $ticket->whatsappNotifications->firstWhere('event_type', 'unverified'))
            <section class="card whatsapp-card">
                <div class="section-heading"><div><div class="eyebrow">PEMBERITAHUAN AWAL</div><h2>Nomor WhatsApp Belum Terverifikasi</h2></div><span class="wa-mark">WA</span></div>
                <p class="small muted">Pesan ini hanya tersedia setelah petugas memastikan bahwa nomor belum terverifikasi di AMS BSrE.</p>
                <div class="message-box"><div class="message-toolbar"><strong>Pratinjau Pesan</strong><button type="button" class="btn btn-copy btn-small" data-copy-target="unverified-wa-message">Salin Pesan</button></div><div id="unverified-wa-message" class="message-preview">{{ $unverifiedMessage }}</div></div>
                @unless($ticket->source === 'simulated')
                    <form target="tte_whatsapp_web" method="post" action="{{ route('tickets.whatsapp.unverified', $ticket) }}">@csrf<button class="btn btn-wa full" type="submit">Buka WhatsApp Web ↗</button></form>
                    @if($unverifiedNotification?->opened_at)
                        <p class="small muted mt">Tautan dibuka: {{ $unverifiedNotification->opened_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</p>
                        @if($unverifiedNotification->confirmed_at)
                            <div class="alert success mt">Pesan dikonfirmasi secara manual oleh petugas.</div>
                        @else
                            <form method="post" action="{{ route('tickets.whatsapp.unverified.confirm', $ticket) }}" class="mt">@csrf<button type="submit" class="btn btn-outline full">Saya Sudah Mengirim Pesan</button></form>
                        @endif
                    @endif
                @endunless
                <p class="small muted mt">Mengirim pesan tidak mengubah status verifikasi di AMS. Lakukan pemeriksaan ulang setelah pemohon menyelesaikan verifikasi.</p>
            </section>
        @endif
        @if($followupMessage)
            @php($followupNotification = $ticket->whatsappNotifications->firstWhere('event_type', 'followup'))
            <section class="card whatsapp-card">
                <div class="section-heading"><div><div class="eyebrow">KOMUNIKASI PEMOHON</div><h2>WhatsApp Tindak Lanjut</h2></div><span class="wa-mark">WA</span></div>
                <p class="small muted">Periksa isi pesan sebelum mengirim. Anda juga dapat menyalin pesan langsung ke clipboard.</p>
                <div class="message-box"><div class="message-toolbar"><strong>Pratinjau Pesan</strong><button type="button" class="btn btn-copy btn-small" data-copy-target="followup-wa-message">Salin Pesan</button></div><div id="followup-wa-message" class="message-preview">{{ $followupMessage }}</div></div>
                @unless($ticket->source === 'simulated')
                    <form target="tte_whatsapp_web" method="post" action="{{ route('tickets.whatsapp.followup', $ticket) }}">@csrf<button class="btn btn-wa full" type="submit">Buka WhatsApp Web ↗</button></form>
                    @if($followupNotification?->opened_at)<p class="muted small mt">Tautan dibuka: {{ $followupNotification->opened_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</p>
                        @if($followupNotification->confirmed_at)<div class="alert success mt">Pengiriman dikonfirmasi secara manual.</div>
                        @else<form method="post" action="{{ route('tickets.whatsapp.followup.confirm', $ticket) }}" class="mt">@csrf<button class="btn btn-outline full" type="submit">Saya Sudah Mengirim Pesan</button></form>@endif
                    @endif
                @endunless
                <p class="small muted mt">Menyalin atau membuka chat bukan bukti pesan diterima.</p>
            </section>
        @endif
        @if($ticket->status === 'Done' && $waMessage)
            @php($notification = $ticket->whatsappNotifications->firstWhere('event_type', 'completed'))
            <section class="card whatsapp-card">
                <div class="section-heading"><div><div class="eyebrow">KOMUNIKASI PEMOHON</div><h2>Notifikasi WhatsApp Selesai</h2></div><span class="wa-mark">WA</span></div>
                <p class="small muted">Isi pesan diambil dari preset admin sesuai layanan dan nama verifikator Anda.</p>
                <div class="message-box"><div class="message-toolbar"><strong>Pratinjau Pesan</strong><button type="button" class="btn btn-copy btn-small" data-copy-target="completed-wa-message">Salin Pesan</button></div><div id="completed-wa-message" class="message-preview">{{ $waMessage }}</div></div>
                @unless($ticket->source === 'simulated')
                    <form target="tte_whatsapp_web" method="post" action="{{ route('tickets.whatsapp', $ticket) }}">@csrf<button class="btn btn-wa full" type="submit">Buka WhatsApp Web ↗</button></form>
                    @if($notification?->opened_at)<p class="muted small mt">Tautan dibuka: {{ $notification->opened_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</p>
                        @if($notification->confirmed_at)<div class="alert success mt">Pengiriman telah dikonfirmasi oleh petugas.</div>
                        @else<form method="post" action="{{ route('tickets.whatsapp.confirm', $ticket) }}" class="mt">@csrf<button class="btn btn-outline full" type="submit">Saya Sudah Mengirim Pesan</button></form>@endif
                    @endif
                @endunless
                <p class="small muted mt">Konfirmasi manual bukan verifikasi delivery WhatsApp.</p>
            </section>
        @endif
    </div>
</div>
@endsection
