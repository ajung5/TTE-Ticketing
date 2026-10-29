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
                <div><div class="field-name">NIP</div><div class="field-value">{{ $ticket->nip ?: '-' }}</div></div>
                <div><div class="field-name">Unit Kerja / OPD</div><div class="field-value">{{ $ticket->opd ?: '-' }}</div></div>
                <div><div class="field-name">Jabatan</div><div class="field-value">{{ $ticket->jabatan ?: '-' }}</div></div>
                <div><div class="field-name">Email Dinas</div><div class="field-value">{{ $ticket->email ?: '-' }}</div></div>
                <div><div class="field-name">Tanggal Masuk</div><div class="field-value">{{ $ticket->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</div></div>
                <div><div class="field-name">Penanggung Jawab</div><div class="field-value">{{ $ticket->claimant?->name ?? 'Belum diambil' }}</div></div>
            </div>
            @if($ticket->keterangan)<div class="note-box"><div class="field-name">Keterangan pemohon</div><div class="pre-wrap">{{ $ticket->keterangan }}</div></div>@endif
        </section>
        <section class="card"><div class="section-heading"><div><div class="eyebrow">JEJAK AKTIVITAS</div><h2>Riwayat Penanganan</h2></div><span class="pill pill-neutral">{{ $ticket->activities->count() }} aktivitas</span></div>
            <div class="timeline">
                @forelse($ticket->activities as $entry)
                    <div class="timeline-item"><div><strong>{{ match($entry->action) {'claimed'=>'Tiket diambil','released'=>'Tiket dilepas','note'=>'Catatan tindak lanjut','completed'=>'Tiket diselesaikan','wa_confirmed'=>'WhatsApp penyelesaian dikonfirmasi','wa_followup_confirmed'=>'WhatsApp tindak lanjut dikonfirmasi',default=>$entry->action} }}</strong><div class="small muted">{{ $entry->actor?->name ?? 'Sistem' }} · {{ $entry->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</div></div>@if($entry->note)<p class="pre-wrap">{{ $entry->note }}</p>@endif</div>
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
                <form class="stack" method="post" action="{{ route('tickets.complete', $ticket) }}">@csrf<label>Hasil penyelesaian<textarea name="resolution_note" rows="4" minlength="10" maxlength="3000" required placeholder="Ringkasan hasil yang sudah dikonfirmasi"></textarea></label><p class="small muted">Selesaikan tiket hanya setelah hasil penanganan terverifikasi.</p><button type="submit" class="btn btn-primary full">Tandai Selesai ✓</button></form>
                @unless($ticket->activities->whereNotIn('action', ['claimed','released'])->count())
                    <form method="post" action="{{ route('tickets.release', $ticket) }}" class="mt">@csrf<button type="submit" class="btn btn-ghost full">Lepas Tiket (belum dikerjakan)</button></form>
                @endunless
            @else
                <div class="alert success">Tiket selesai pada {{ $ticket->completed_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }}.</div>
                <div class="field-name">Catatan hasil</div><p class="pre-wrap">{{ $ticket->resolution_note ?: 'Tiket lama: tidak ada catatan hasil.' }}</p>
            @endif
        </section>
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
        @if($ticket->status === 'Done')
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
