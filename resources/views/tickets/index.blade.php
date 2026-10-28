@extends('layouts.app')
@section('title', 'Antrean Tiket')
@section('content')
<div class="page-heading"><div><div class="eyebrow">DASHBOARD VERIFIKATOR</div><h1>Antrean Permohonan TTE</h1><p class="muted">Ambil tiket yang tersedia, catat tindak lanjut, dan selesaikan pekerjaan.</p></div><span class="pill pill-blue">Self-Claim Aktif</span></div>
<div class="stat-grid three">
    <div class="stat-card"><div class="stat-name">Tiket Belum Diambil</div><div class="stat-number">{{ $stats['new'] }}</div><div class="stat-foot">Antrean bersama</div></div>
    <div class="stat-card"><div class="stat-name">Sedang Saya Kerjakan</div><div class="stat-number">{{ $stats['mine'] }}</div><div class="stat-foot">Tiket milik Anda</div></div>
    <div class="stat-card"><div class="stat-name">Selesai oleh Saya</div><div class="stat-number">{{ $stats['completed'] }}</div><div class="stat-foot">Total penyelesaian</div></div>
</div>
<section class="card">
    <div class="section-heading"><h2>Daftar Tiket Saya dan Antrean</h2><span class="muted small">{{ $tickets->total() }} data</span></div>
    <form class="filters" method="get" action="{{ route('tickets.index') }}">
        <input name="q" type="search" value="{{ request('q') }}" placeholder="Cari nama / nomor tiket">
        <select name="status"><option value="">Semua status</option>@foreach(['Open'=>'Baru','In Progress'=>'Diproses','Done'=>'Selesai'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
        <select name="layanan"><option value="">Semua layanan</option>@foreach(['Reset Passphrase','Pembaruan','Pendaftaran'] as $service)<option value="{{ $service }}" @selected(request('layanan') === $service)>{{ $service }}</option>@endforeach</select>
        <button class="btn btn-primary" type="submit">Filter</button><a href="{{ route('tickets.index') }}" class="btn btn-ghost">Reset</a>
    </form>
    <div class="table-scroll"><table><thead><tr><th>Tiket</th><th>Pemohon / OPD</th><th>Layanan</th><th>Status</th><th>Verifikator</th><th>Aksi</th></tr></thead><tbody>
    @forelse($tickets as $ticket)
        <tr>
            <td><strong>{{ $ticket->ticket_number ?? '#'.$ticket->id }}</strong><div class="small muted">{{ $ticket->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</div></td>
            <td><strong>{{ $ticket->nama_user }}</strong><div class="small muted">{{ $ticket->opd ?: 'OPD belum diisi' }}</div></td>
            <td>{{ $ticket->service_label }}</td>
            <td><span class="pill {{ $ticket->status === 'Open' ? 'pill-neutral' : ($ticket->status === 'Done' ? 'pill-green' : 'pill-blue') }}">{{ $ticket->status_label }}</span></td>
            <td>{{ $ticket->claimant?->name ?? 'Belum diambil' }}</td>
            <td>
                @if($ticket->status === 'Open')
                    <form method="post" action="{{ route('tickets.claim', $ticket) }}">@csrf<button class="btn btn-primary btn-small" type="submit">Ambil Tiket</button></form>
                @elseif($ticket->claimed_by === auth()->id())
                    <a class="btn btn-outline btn-small" href="{{ route('tickets.show', $ticket) }}">Detail Tiket</a>
                @else
                    <span class="muted small">Ditangani petugas lain</span>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">Belum ada tiket sesuai filter.</td></tr>
    @endforelse
    </tbody></table></div>
    <div class="pagination">{{ $tickets->links() }}</div>
</section>
@endsection
