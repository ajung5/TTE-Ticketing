@extends('layouts.app')
@section('title', 'Dashboard Admin')
@section('content')
<div class="page-heading"><div><div class="eyebrow">DASHBOARD ADMINISTRATOR</div><h1>Statistik Layanan TTE</h1><p class="muted">Pemantauan agregat. Admin tidak dapat mengakses detail atau menugaskan tiket.</p></div><span class="pill pill-blue">Monitoring</span></div>
<div class="stat-grid">
    <div class="stat-card"><div class="stat-name">Total Permohonan</div><div class="stat-number">{{ $stats['total'] }}</div><div class="stat-foot">Semua layanan</div></div>
    <div class="stat-card"><div class="stat-name">Belum Diambil</div><div class="stat-number">{{ $stats['new'] }}</div><div class="stat-foot">Antrean saat ini</div></div>
    <div class="stat-card"><div class="stat-name">Sedang Diproses</div><div class="stat-number">{{ $stats['active'] }}</div><div class="stat-foot">Aktif di verifikator</div></div>
    <div class="stat-card"><div class="stat-name">Selesai</div><div class="stat-number">{{ $stats['done'] }}</div><div class="stat-foot">Telah ditutup</div></div>
</div>
<div class="columns">
    <section class="card"><h2>Komposisi Jenis Layanan</h2><div class="bar-list">
        @foreach(['Reset Passphrase'=>'Reset Passphrase','Pembaruan'=>'Pembaruan Sertifikat','Pendaftaran'=>'Pendaftaran Baru'] as $code=>$label)
            @php($count = (int) ($services[$code] ?? 0))
            <div><div class="bar-label"><span>{{ $label }}</span><strong>{{ $count }}</strong></div><div class="bar-track"><div class="bar-value" style="width:{{ $stats['total'] > 0 ? round($count / $stats['total'] * 100) : 0 }}%"></div></div></div>
        @endforeach
    </div></section>
    <section class="card"><h2>Informasi Operasional</h2><div class="note-box"><strong>Sistem Self-Claim</strong><p class="small muted">Verifikator mengambil sendiri tiket dari antrean. Admin hanya memantau statistik dan membantu pemulihan password.</p></div><div class="note-box mt"><strong>Pengiriman WhatsApp</strong><p class="small muted">Tahap MVP menggunakan Click-to-Chat, bukan pengiriman WhatsApp otomatis oleh server.</p></div></section>
</div>
<section class="card mt"><div class="section-heading"><div><h2>Statistik &amp; Akun Verifikator</h2><p class="small muted">Reset password mengirimkan tautan sekali pakai, bukan menampilkan password.</p></div></div>
    <div class="table-scroll"><table><thead><tr><th>Nama</th><th>Inisial</th><th>Tiket Ditangani</th><th>Aktif</th><th>Selesai</th><th>Pemulihan Akun</th></tr></thead><tbody>
    @forelse($verifiers as $verifier)
        @php($c = $counts[$verifier->id] ?? null)
        <tr><td><strong>{{ $verifier->name }}</strong><div class="small muted">{{ $verifier->email }}</div></td><td>{{ $verifier->initials ?: '-' }}</td><td>{{ $c?->total ?? 0 }}</td><td>{{ $c?->active_count ?? 0 }}</td><td>{{ $c?->done_count ?? 0 }}</td><td><form method="post" action="{{ route('admin.password-reset', $verifier) }}" onsubmit="return confirm('Kirim tautan reset password untuk akun ini?')">@csrf<button type="submit" class="btn btn-outline btn-small">Kirim Link Reset</button></form></td></tr>
    @empty <tr><td colspan="6" class="empty">Belum ada verifikator.</td></tr> @endforelse
    </tbody></table></div>
</section>
@endsection
