@extends('layouts.app')
@section('title', 'Statistik Layanan')
@section('content')
    <style>
        .stat-grid.admin-statistics {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
    </style>
    <div class="page-heading">
        <div>
            <div class="eyebrow">OVERVIEW / ADMINISTRATOR</div>
            <h1>Statistik Layanan TTE</h1>
            <p class="muted">Ringkasan kinerja dan antrean permohonan lintas layanan.</p>
        </div>
        <span class="pill pill-blue">● Monitoring aktif</span>
    </div>
    <div class="stat-grid admin-statistics">
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
                {{ $stats['total'] ? number_format(($stats['done'] / $stats['total']) * 100, 1, ',', '.') : 0 }}% dari
                seluruh tiket</div>
        </div>
        <div class="stat-card">
            <div class="stat-top"><span class="stat-icon stat-amber">↩</span><span class="stat-micro">DIKEMBALIKAN</span>
            </div>
            <div class="stat-name">Perlu Pengajuan Ulang</div>
            <div class="stat-number">{{ number_format($stats['returned'], 0, ',', '.') }}</div>
            <div class="stat-foot">Tidak dihitung sebagai tiket selesai</div>
        </div>
    </div>
    <details class="card mt tte-collapsible tte-health-disclosure">
        <summary class="section-heading tte-disclosure-heading">
            <div>
                <div class="eyebrow">MONITORING OPERASIONAL</div>
                <h2>Kesehatan Sistem TTE</h2>
                <p class="small muted">Status direktori, SMTP, dan antrean pekerjaan. Tidak mengubah konfigurasi server.</p>
            </div>
        </summary>
        <div class="tte-admin-health-list">
            <div class="tte-health-item">
                <strong>Direktori Email</strong>
                <span
                    @class([
                        'tte-health-status',
                        'is-warning' => !$health['directory_fresh']
                    ])>{{ $health['directory_fresh'] ? 'Data tersedia' : 'Perlu perhatian' }}</span>
                <p>{{ number_format($health['directory_count'], 0, ',', '.') }} baris · Sinkronisasi terakhir:
                    {{ $health['directory_last'] }}.</p>
                <p>Batas usia {{ $health['directory_max_age'] }} jam. Akses Google Sheets:
                    {{ $health['sheet_configured'] ? 'credential terkonfigurasi' : 'belum lengkap' }}.</p>
            </div>
            <div class="tte-health-item">
                <strong>Notifikasi Email</strong>
                <span
                    @class(['tte-health-status', 'is-warning' => !$health['smtp_ready']])>{{ $health['smtp_ready'] ? 'Konfigurasi SMTP lengkap' : 'Konfigurasi perlu diperiksa' }}</span>
                <p>Mailer: {{ $health['mailer'] }}. Status konfigurasi, bukan bukti email terkirim.</p>
            </div>
            <div class="tte-health-item">
                <strong>Queue &amp; Scheduler</strong>
                <span @class([
                    'tte-health-status',
                    'is-warning' => ($health['failed_jobs'] ?? 0) > 0
                ])>Queue: {{ $health['queue_driver'] }}</span>
                <p>Menunggu: {{ $health['pending_jobs'] === null ? 'Tidak tersedia' : $health['pending_jobs'] }} · Gagal:
                    {{ $health['failed_jobs'] === null ? 'Tidak tersedia' : $health['failed_jobs'] }}.</p>
                <p>Status cron/worker tidak dapat dipastikan hanya dari konfigurasi.</p>
            </div>
            <div class="tte-health-item">
                <strong>Antrean Butuh Perhatian</strong>
                <span class="tte-health-status">Indikator {{ $attention['hours'] }} jam (bukan SLA)</span>
                <p>{{ $attention['waiting'] }} belum diambil melebihi ambang · {{ $attention['idle'] }} diproses tanpa
                    pembaruan tiket melebihi ambang.</p>
                <p>7 hari terakhir: {{ $attention['weekly_in'] }} tiket masuk · {{ $attention['weekly_done'] }} tiket
                    diselesaikan (berdasarkan tanggal selesai).</p>
            </div>
        </div>
    </details>
    <details class="card mt tte-collapsible tte-ams-disclosure">
        <summary class="section-heading tte-disclosure-heading">
            <div>
                <div class="eyebrow">PEMANTAUAN AMS BSrE</div>
                <h2>Hasil Pemeriksaan Akun dan Sertifikat</h2>
                <p class="small muted">Hasil pemeriksaan manual terakhir; bukan pemantauan real-time AMS.
                    Setiap indikator dihitung secara independen sehingga tidak dijumlahkan menjadi total tiket.</p>
            </div>
        </summary>
        <div class="stat-grid admin-statistics">
            @foreach ([
            'Akun Sudah Aktivasi' => $ams->activated,
            'Akun Belum Aktivasi' => $ams->not_activated,
            'WA Belum Terverifikasi' => $ams->wa_unverified,
            'WA Terverifikasi' => $ams->wa_verified,
            'SE Belum Terbit' => $ams->not_issued,
            'SE Aktif' => $ams->cert_active,
            'SE Kedaluwarsa' => $ams->cert_expired,
            'SE Revoke (Penerbitan Ulang)' => $ams->cert_revoked
        ] as $label => $total)
                <div class="stat-card">
                    <div class="stat-name">{{ $label }}</div>
                    <div class="stat-number">{{ number_format((int) $total, 0, ',', '.') }}</div>
                    <div class="stat-foot">Berdasarkan data pemeriksaan AMS</div>
                </div>
            @endforeach
        </div>
    </details>
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
            <p class="muted">Atur isi pesan yang disiapkan bagi pemohon saat tiket ditindaklanjuti atau
                diselesaikan.</p>
            <div class="note-box"><strong>{{ count(\App\Models\WaMessageTemplate::EVENTS) }} jenis notifikasi siap
                    dikelola</strong>
                <p class="small muted">Notifikasi mengikuti kondisi AMS, termasuk Revoke dan hasil aktual pelayanan.</p>
            </div>
            <a class="btn btn-primary full mt" href="{{ route('admin.templates.index') }}">Kelola Preset WhatsApp <span
                    aria-hidden="true">→</span></a>
            <p class="small muted mt">Admin tidak dapat mengambil atau memproses tiket.</p>
        </section>
    </div>
    <details class="card mt tte-collapsible tte-outcome-disclosure">
        <summary class="section-heading tte-disclosure-heading">
            <div>
                <div class="eyebrow">HASIL AKTUAL</div>
                <h2>Pelayanan yang Benar-Benar Diselesaikan</h2>
                <p class="small muted">Dihitung berdasarkan hasil akhir pelayanan, bukan jenis layanan awal pemohon.
                    Tiket lama tanpa hasil terstruktur tidak disimpulkan otomatis.</p>
            </div>
        </summary>
        <div class="stat-grid admin-statistics">
            @foreach ([
            'Reset Passphrase Selesai' => $outcomes['passphrase_reset'] ?? 0,
            'SE Baru Terbit' => $outcomes['certificate_issued'] ?? 0,
            'SE Diperbarui' => $outcomes['certificate_renewed'] ?? 0,
            'SE Diterbitkan Ulang' => $outcomes['certificate_reissued'] ?? 0,
            'Pendaftaran Diproses (Administratif)' => $outcomes['registration_processed'] ?? 0
        ] as $label => $total)
                <div class="stat-card">
                    <div class="stat-name">{{ $label }}</div>
                    <div class="stat-number">{{ number_format((int) $total, 0, ',', '.') }}</div>
                    <div class="stat-foot">Hasil terkonfirmasi pada tiket selesai</div>
                </div>
            @endforeach
        </div>
    </details>
    <section class="card mt">
        <div class="section-heading">
            <div>
                <div class="eyebrow">KINERJA TIM</div>
                <h2>Statistik Verifikator</h2>
                <p class="small muted">Data agregat penanganan petugas. Kelola akun dan reset password di menu Manajemen
                    Verifikator.</p>
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
                        <th>Perlu Pengajuan Ulang</th>

                    </tr>
                </thead>
                <tbody>
                    @forelse($verifiers as $verifier)
                        @php($c = $counts[$verifier->id] ?? null)
                        <tr>
                            <td>
                                <div class="person-row"><span
                                        class="person-avatar">{{ strtoupper(substr($verifier->initials ?: $verifier->name, 0, 2)) }}</span>
                                    <span><strong>{{ $verifier->name }}</strong>
                                        <div class="small muted">{{ $verifier->email }}</div>
                                    </span>
                                </div>
                            </td>
                            <td>{{ $verifier->initials ?: '-' }}</td>
                            <td><strong>{{ $c?->total ?? 0 }}</strong></td>
                            <td>{{ $c?->active_count ?? 0 }}</td>
                            <td><span class="pill pill-green">{{ $c?->done_count ?? 0 }} selesai</span></td>
                            <td>{{ $c?->returned_count ?? 0 }}</td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty">Belum ada verifikator.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
