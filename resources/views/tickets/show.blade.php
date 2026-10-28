@extends('layouts.app')
@section('title', 'Detail Tiket')
@section('content')
<div class="page-heading"><div><a href="{{ route('tickets.index') }}" class="small">← Kembali ke antrean</a><h1 class="mt">{{ $ticket->ticket_number ?? '#'.$ticket->id }}</h1><p class="muted">{{ $ticket->service_label }}</p></div><span class="pill {{ $ticket->status === 'Done' ? 'pill-green' : ($ticket->status === 'Open' ? 'pill-neutral' : 'pill-blue') }}">{{ $ticket->status_label }}</span></div>
<div class="columns">
    <div class="stack">
        <section class="card"><h2>Detail Permohonan</h2><div class="detail-grid">
            <div><div class="field-name">Nama pemohon</div><div class="field-value">{{ $ticket->nama_user }}</div></div>
            <div><div class="field-name">Nomor WhatsApp</div><div class="field-value">{{ $ticket->no_wa }}</div></div>
            <div><div class="field-name">NIP</div><div class="field-value">{{ $ticket->nip ?: '-' }}</div></div>
            <div><div class="field-name">OPD</div><div class="field-value">{{ $ticket->opd ?: '-' }}</div></div>
            <div><div class="field-name">Jabatan</div><div class="field-value">{{ $ticket->jabatan ?: '-' }}</div></div>
            <div><div class="field-name">Email dinas</div><div class="field-value">{{ $ticket->email ?: '-' }}</div></div>
            <div><div class="field-name">Tanggal masuk</div><div class="field-value">{{ $ticket->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</div></div>
            <div><div class="field-name">Verifikator</div><div class="field-value">{{ $ticket->claimant?->name ?? 'Belum diambil' }}</div></div>
        </div>@if($ticket->keterangan)<div class="note-box"><div class="field-name">Keterangan pemohon</div><div class="pre-wrap">{{ $ticket->keterangan }}</div></div>@endif</section>
        <section class="card"><h2>Riwayat Tindak Lanjut</h2><div class="timeline">
            @forelse($ticket->activities as $entry)<div class="timeline-item"><div><strong>{{ match($entry->action) {'claimed'=>'Tiket diambil','released'=>'Tiket dilepas','note'=>'Catatan tindak lanjut','completed'=>'Tiket diselesaikan','wa_confirmed'=>'WhatsApp penyelesaian dikonfirmasi','wa_followup_confirmed'=>'WhatsApp tindak lanjut dikonfirmasi',default=>$entry->action} }}</strong><div class="small muted">{{ $entry->actor?->name ?? 'Sistem' }} • {{ $entry->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</div></div>@if($entry->note)<p class="pre-wrap">{{ $entry->note }}</p>@endif</div>
            @empty <p class="muted">Belum ada riwayat.</p> @endforelse
        </div></section>
    </div>
    <div class="stack">
        <section class="card"><h2>Penanganan Tiket</h2>
            @if($ticket->status === 'Open')
                <p class="muted">Tiket belum diambil. Ambil untuk mulai memprosesnya.</p>
                <form method="post" action="{{ route('tickets.claim', $ticket) }}">@csrf<button class="btn btn-primary full" type="submit">Ambil Tiket Ini</button></form>
            @elseif($ticket->status === 'In Progress')
                <div class="note-box"><strong>Ditangani oleh {{ $ticket->claimant?->name }}</strong><p class="small muted">Catatan proses akan menjadi bagian dari audit trail.</p></div>
                <form class="stack mt" method="post" action="{{ route('tickets.note', $ticket) }}">@csrf<label>Catatan tindak lanjut<textarea name="note" rows="4" maxlength="3000" required placeholder="Contoh: dokumen diverifikasi, menunggu konfirmasi BSrE"></textarea></label><button type="submit" class="btn btn-outline full">Simpan Catatan</button></form>
                <hr><form class="stack" method="post" action="{{ route('tickets.complete', $ticket) }}">@csrf<label>Hasil penyelesaian<textarea name="resolution_note" rows="4" minlength="10" maxlength="3000" required placeholder="Ringkasan langkah dan hasil yang sudah diverifikasi"></textarea></label><p class="small muted">Pilih Selesai hanya setelah hasil penanganan benar-benar terkonfirmasi.</p><button type="submit" class="btn btn-primary full">Tandai Selesai</button></form>
                @unless($ticket->activities->whereNotIn('action', ['claimed','released'])->count())
                    <form method="post" action="{{ route('tickets.release', $ticket) }}" class="mt">@csrf<button type="submit" class="btn btn-ghost full">Lepas Tiket (belum dikerjakan)</button></form>
                @endunless
            @else
                <div class="alert success">Tiket selesai pada {{ $ticket->completed_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }}.</div>
                <div class="field-name">Catatan hasil</div><p class="pre-wrap">{{ $ticket->resolution_note ?: 'Tiket lama: tidak ada catatan hasil.' }}</p>
            @endif
        </section>
        @if($ticket->status === 'In Progress' && $ticket->activities->contains('action', 'note'))
            <section class="card"><h2>WhatsApp Tindak Lanjut</h2>
                <p class="small muted">Pesan otomatis menyesuaikan layanan dan identitas verifikator. Kirim secara manual setelah memeriksa isinya.</p>
                @php($followupNotification = $ticket->whatsappNotifications->firstWhere('event_type', 'followup'))
                <form target="_blank" method="post" action="{{ route('tickets.whatsapp.followup', $ticket) }}">@csrf<button class="btn btn-wa full" type="submit">Buka WhatsApp Tindak Lanjut</button></form>
                @if($followupNotification?->opened_at)
                    <p class="muted small mt">Tautan WhatsApp dibuka: {{ $followupNotification->opened_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</p>
                    @if($followupNotification->confirmed_at)
                        <div class="alert success mt">Petugas mengonfirmasi pengiriman tindak lanjut.</div>
                    @else
                        <form method="post" action="{{ route('tickets.whatsapp.followup.confirm', $ticket) }}" class="mt">@csrf<button class="btn btn-outline full" type="submit">Saya Sudah Mengirim Pesan</button></form>
                    @endif
                @endif
                <p class="small muted mt">Konfirmasi manual bukan bukti pesan sampai ke penerima.</p>
            </section>
        @endif
        @if($ticket->status === 'Done')
            <section class="card"><h2>Notifikasi WhatsApp</h2><p class="small muted">Pesan dibuat otomatis sesuai layanan dan verifikator. Pengiriman tetap manual.</p>
                <div class="message-preview">{{ $waMessage }}</div>
                @php($notification = $ticket->whatsappNotifications->firstWhere('event_type', 'completed'))
                <form target="_blank" method="post" action="{{ route('tickets.whatsapp', $ticket) }}">@csrf<button class="btn btn-wa full" type="submit">Buka WhatsApp dengan Pesan</button></form>
                @if($notification?->opened_at)
                    <p class="muted small mt">WhatsApp dibuka: {{ $notification->opened_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</p>
                    @if($notification->confirmed_at)
                        <div class="alert success mt">Dikonfirmasi petugas pada {{ $notification->confirmed_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}.</div>
                    @else
                        <form method="post" action="{{ route('tickets.whatsapp.confirm', $ticket) }}" class="mt">@csrf<button class="btn btn-outline full" type="submit">Saya Sudah Mengirim Pesan</button></form>
                    @endif
                @endif
                <p class="small muted mt">Konfirmasi manual bukan verifikasi pesan diterima oleh pemohon.</p>
            </section>
        @endif
    </div>
</div>
@endsection
