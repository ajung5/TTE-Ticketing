@extends('layouts.app')
@section('title', 'Antrean Tiket')
@section('content')
<div class="page-heading"><div><div class="eyebrow">WORKSPACE / VERIFIKATOR</div><h1>Antrean Permohonan TTE</h1><p class="muted">Lihat antrean, ambil tiket secara mandiri, dan pantau pekerjaan Anda.</p></div><span class="pill pill-blue">● Self-Claim Aktif</span></div>
<div class="stat-grid three">
    <div class="stat-card"><div class="stat-top"><span class="stat-icon stat-amber">◷</span><span class="stat-micro">ANTREAN BERSAMA</span></div><div class="stat-name">Belum Diambil</div><div class="stat-number">{{ number_format($stats['new'], 0, ',', '.') }}</div><div class="stat-foot">Tiket menunggu penanganan</div></div>
    <div class="stat-card"><div class="stat-top"><span class="stat-icon stat-sky">↻</span><span class="stat-micro">PEKERJAAN SAYA</span></div><div class="stat-name">Sedang Dikerjakan</div><div class="stat-number">{{ number_format($stats['mine'], 0, ',', '.') }}</div><div class="stat-foot">Tiket diambil oleh Anda</div></div>
    <div class="stat-card"><div class="stat-top"><span class="stat-icon stat-emerald">✓</span><span class="stat-micro">HASIL SAYA</span></div><div class="stat-name">Selesai</div><div class="stat-number">{{ number_format($stats['completed'], 0, ',', '.') }}</div><div class="stat-foot">Tiket telah diselesaikan</div></div>
</div>
<section class="card">
    <div class="section-heading"><div><div class="eyebrow">MANAJEMEN PERMOHONAN</div><h2>Daftar Tiket dan Antrean</h2><p class="small muted">Hanya tiket baru dan tiket milik Anda yang ditampilkan.</p></div><span class="pill pill-neutral">{{ $tickets->total() }} hasil</span></div>
    <form class="filters" method="get" action="{{ route('tickets.index') }}">
        <input name="q" type="search" value="{{ request('q') }}" placeholder="Cari nama pemohon / nomor tiket" aria-label="Cari tiket">
        <select name="status" aria-label="Filter status"><option value="">Semua Status</option>@foreach(['Open'=>'Baru','In Progress'=>'Diproses','Done'=>'Selesai'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
        <select name="layanan" aria-label="Filter layanan"><option value="">Semua Layanan</option>@foreach(['Reset Passphrase'=>'Reset Passphrase','Pembaruan'=>'Pembaruan','Pendaftaran'=>'Pendaftaran'] as $service => $label)<option value="{{ $service }}" @selected(request('layanan') === $service)>{{ $label }}</option>@endforeach</select>
        <button class="btn btn-primary" type="submit">Terapkan Filter</button><a href="{{ route('tickets.index') }}" class="btn btn-ghost">Reset</a>
    </form>
    <div class="table-scroll"><table class="ticket-table"><thead><tr><th>Tiket / Tanggal</th><th>Pemohon / Unit</th><th>Layanan</th><th>Status</th><th>Verifikator</th><th>Aksi</th></tr></thead><tbody>
        @forelse($tickets as $ticket)
            <tr>
                <td><strong class="ticket-number">{{ $ticket->ticket_number ?? '#'.$ticket->id }}</strong><div class="small muted">{{ $ticket->created_at->timezone('Asia/Jakarta')->format('d M Y · H:i') }}</div>@if($ticket->source === 'simulated')<span class="demo-label">DATA SIMULASI</span>@endif</td>
                <td><strong>{{ $ticket->nama_user }}</strong><div class="small muted">{{ $ticket->opd ?: 'Belum ada unit kerja' }}</div></td>
                <td><span class="service-chip">{{ $ticket->service_label }}</span></td>
                <td><span class="pill {{ $ticket->status === 'Open' ? 'pill-neutral' : ($ticket->status === 'Done' ? 'pill-green' : 'pill-blue') }}">{{ $ticket->status_label }}</span></td>
                <td>{{ $ticket->claimant?->name ?? 'Belum ada' }}</td>
                <td>@if($ticket->status === 'Open')<form method="post" action="{{ route('tickets.claim', $ticket) }}">@csrf<button class="btn btn-primary btn-small" type="submit">Ambil Tiket →</button></form>@elseif($ticket->claimed_by === auth()->id())<a class="btn btn-outline btn-small" href="{{ route('tickets.show', $ticket) }}">Lihat Detail →</a>@else<span class="small muted">Petugas lain</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">Tidak ada tiket yang sesuai dengan filter.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="pagination">{{ $tickets->links() }}</div>
</section>
@endsection
