@extends('layouts.app')
@section('title', 'Statistik Layanan')
@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">OVERVIEW / ADMINISTRATOR</div>
            <h1>Statistik Layanan TTE</h1>
            <p class="muted">Ringkasan kinerja dan antrean permohonan lintas layanan.</p>
        </div>
        <span class="pill pill-blue">● Monitoring aktif</span>
    </div>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-top"><span class="stat-icon stat-indigo">▤</span><span class="stat-micro">KESELURUHAN</span></div>
            <div class="stat-name">Total Permohonan</div>
            <div class="stat-number">{{ number_format($stats['total'], 0, ',', '.') }}</div>
            <div class="stat-foot">Semua sumber data</div>
        </div>
        <div class="stat-card">
            <div class="stat-top"><span class="stat-icon stat-amber">◷</span><span class="stat-micro">ANTREAN</span></div>
            <div class="stat-name">Belum Diambil</div>
            <div class="stat-number">{{ number_format($stats['new'], 0, ',', '.') }}</div>
            <div class="stat-foot">Menunggu verifikator</div>
        </div>
        <div class="stat-card">
            <div class="stat-top"><span class="stat-icon stat-sky">↻</span><span class="stat-micro">BERJALAN</span></div>
            <div class="stat-name">Sedang Diproses</div>
            <div class="stat-number">{{ number_format($stats['active'], 0, ',', '.') }}</div>
            <div class="stat-foot">Sudah diambil petugas</div>
        </div>
        <div class="stat-card">
            <div class="stat-top"><span class="stat-icon stat-emerald">✓</span><span class="stat-micro">SELESAI</span></div>
            <div class="stat-name">Tiket Selesai</div>
            <div class="stat-number">{{ number_format($stats['done'], 0, ',', '.') }}</div>
            <div class="stat-foot">
                {{ $stats['total'] ? number_format(($stats['done'] / $stats['total']) * 100, 1, ',', '.') : 0 }}% dari seluruh
                tiket</div>
        </div>
    </div>
    <div class="columns overview-columns">
        <section class="card">
            <div class="section-heading">
                <div>
                    <div class="eyebrow">DISTRIBUSI PERMOHONAN</div>
                    <h2>Komposisi Jenis Layanan</h2>
                </div><span class="pill pill-neutral">3 layanan</span>
            </div>
            <div class="bar-list">
                @foreach (['Reset Passphrase' => 'Reset Passphrase', 'Pembaruan' => 'Pembaruan Sertifikat', 'Pendaftaran' => 'Pendaftaran Baru'] as $code => $label)
                    @php($count = (int) ($services[$code] ?? 0))
                    @php($percent = $stats['total'] > 0 ? round(($count / $stats['total']) * 100, 1) : 0)
                    <div class="bar-item">
                        <div class="bar-label"><span>{{ $label }}</span><span><strong>{{ $count }}</strong>
                                <small class="muted">({{ $percent }}%)</small></span></div>
                        <div class="bar-track">
                            <div class="bar-value" style="width:{{ $percent }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
        <section class="card">
            <div class="section-heading">
                <div>
                    <div class="eyebrow">PUSAT PENGATURAN</div>
                    <h2>Komunikasi WhatsApp</h2>
                </div><span class="stat-icon stat-emerald">✉</span>
            </div>
            <p class="muted">Atur isi pesan otomatis yang disiapkan bagi pemohon saat tiket ditindaklanjuti atau
                diselesaikan.</p>
            <div class="note-box"><strong>6 preset siap dikelola</strong>
                <p class="small muted">Tiga jenis layanan × dua tahap pemberitahuan. Pesan mencantumkan identitas
                    verifikator dari akun login.</p>
            </div>
            <a class="btn btn-primary full mt" href="{{ route('admin.templates.index') }}">Kelola Preset WhatsApp <span
                    aria-hidden="true">→</span></a>
            <p class="small muted mt">Admin tidak dapat mengambil atau memproses tiket.</p>
        </section>
    </div>
    <section class="card mt">
        <div class="section-heading">
            <div>
                <div class="eyebrow">KINERJA TIM</div>
                <h2>Statistik Verifikator</h2>
                <p class="small muted">Pemulihan password melalui tautan sekali pakai, tidak menampilkan password lama.</p>
            </div><span class="pill pill-neutral">{{ $verifiers->count() }} verifikator</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Verifikator</th>
                        <th>Inisial</th>
                        <th>Tiket Ditangani</th>
                        <th>Aktif</th>
                        <th>Selesai</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($verifiers as $verifier)
                        @php($c = $counts[$verifier->id] ?? null)
                        <tr>
                            <td>
                                <div class="person-row"><span
                                        class="person-avatar">{{ strtoupper(substr($verifier->initials ?: $verifier->name, 0, 2)) }}</span><span><strong>{{ $verifier->name }}</strong>
                                        <div class="small muted">{{ $verifier->email }}</div>
                                    </span></div>
                            </td>
                            <td>{{ $verifier->initials ?: '-' }}</td>
                            <td><strong>{{ $c?->total ?? 0 }}</strong></td>
                            <td>{{ $c?->active_count ?? 0 }}</td>
                            <td><span class="pill pill-green">{{ $c?->done_count ?? 0 }} selesai</span></td>
                            <td>
                                <form method="post" action="{{ route('admin.password-reset', $verifier) }}"
                                    onsubmit="return confirm('Kirim tautan reset password untuk akun ini?')">@csrf<button
                                        class="btn btn-outline btn-small" type="submit">Reset Password</button></form>
                            </td>
                        </tr>
                    @empty <tr>
                            <td colspan="6" class="empty">Belum ada verifikator.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
