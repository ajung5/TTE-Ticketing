@extends('layouts.app')
@section('title', 'Laporan Tiket')
@section('content')
    <style>
        .report-page {
            display: grid;
            gap: 19px
        }

        .report-heading {
            margin-bottom: 0
        }

        .report-controls {
            display: grid;
            grid-template-columns: minmax(160px, 1fr) repeat(2, minmax(140px, 1fr)) auto;
            gap: 13px;
            align-items: end
        }

        .report-controls label {
            display: grid;
            gap: 7px;
            font-size: 12px;
            font-weight: 700;
            color: #52617b
        }

        .report-controls input,
        .report-controls select {
            min-height: 43px;
            width: 100%;
            border: 1px solid #d6e1ef;
            border-radius: 9px;
            padding: 9px 11px;
            background: #fff;
            color: #273c59;
            font: inherit
        }

        .report-controls .report-action {
            min-height: 43px
        }

        .report-custom-fields {
            display: contents
        }

        .report-page [hidden] {
            display: none !important
        }

        .report-metrics {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px
        }

        .report-metric {
            background: #fff;
            border: 1px solid #e2e9f2;
            border-radius: 13px;
            padding: 16px
        }

        .report-metric strong {
            display: block;
            font-size: 25px;
            line-height: 1.4;
            color: #243753;
            margin-top: 5px
        }

        .report-metric small {
            color: #657894;
            line-height: 1.4
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px
        }

        .report-table th,
        .report-table td {
            text-align: left;
            padding: 11px 12px;
            border-bottom: 1px solid #eaf0f6;
            vertical-align: top
        }

        .report-table th {
            color: #53647c;
            background: #f7faff;
            font-weight: 800;
            white-space: nowrap
        }

        .report-table .num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums
        }

        .report-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            align-items: center
        }

        .report-actions .btn {
            min-height: 40px
        }

        .report-period-note {
            color: #54657d;
            font-size: 12px;
            line-height: 1.6
        }

        .report-info {
            background: #f7faff;
            border: 1px solid #dfe9f6;
            border-radius: 10px;
            padding: 12px 14px;
            color: #3c5476;
            font-size: 12px;
            line-height: 1.65
        }

        .report-service-bar {
            height: 8px;
            border-radius: 8px;
            background: #e8eef7;
            overflow: hidden;
            min-width: 90px
        }

        .report-service-bar span {
            display: block;
            height: 100%;
            border-radius: 8px;
            background: #4f75c5
        }

        .report-print-meta {
            display: none
        }

        @media(max-width:1050px) {
            .report-metrics {
                grid-template-columns: repeat(3, minmax(0, 1fr))
            }
        }

        @media(max-width:730px) {
            .report-controls {
                grid-template-columns: 1fr 1fr
            }

            .report-controls>:first-child {
                grid-column: 1/-1
            }

            .report-action {
                grid-column: 1/-1
            }

            .report-metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }
        }

        @media(max-width:480px) {
            .report-controls {
                grid-template-columns: 1fr
            }

            .report-controls>* {
                grid-column: 1/-1
            }

            .report-metrics {
                grid-template-columns: 1fr 1fr
            }

            .report-metric {
                padding: 12px
            }

            .report-metric strong {
                font-size: 21px
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 15mm
            }

            body {
                background: #fff !important;
                color: #111 !important;
                font-size: 10pt
            }

            .sidebar,
            .topbar,
            .sidebar-help,
            .report-filter-card,
            .report-actions,
            .report-detail-card,
            .report-no-print,
            .clipboard-status {
                display: none !important
            }

            .shell,
            .main,
            .content,
            .report-page {
                display: block !important;
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important
            }

            .report-heading {
                margin: 0 0 12px !important
            }

            .report-heading h1 {
                font-size: 18pt !important
            }

            .report-print-meta {
                display: block;
                margin: 8px 0 16px;
                font-size: 9pt
            }

            .report-metrics {
                display: grid !important;
                grid-template-columns: repeat(5, 1fr);
                gap: 6px;
                margin: 12px 0
            }

            .report-metric {
                border: 1px solid #bbb;
                break-inside: avoid;
                padding: 8px
            }

            .report-metric strong {
                font-size: 16pt
            }

            .report-page>.card,
            .report-page>.report-metrics {
                break-inside: avoid;
                margin-bottom: 12px
            }

            .report-table th,
            .report-table td {
                padding: 7px 8px;
                font-size: 9pt
            }

            .report-info {
                border: 1px solid #bbb;
                background: #fff;
                color: #333
            }
        }
    </style>
    <div class="report-page">
        <div class="page-heading report-heading">
            <div>
                <div class="eyebrow">ADMIN / EVALUASI LAYANAN</div>
                <h1>Laporan Tiket TTE</h1>
                <p class="muted">Rekap permohonan layanan berdasarkan bulan atau periode yang ditentukan.</p>
            </div>
            <div class="report-actions">
                <a class="btn btn-outline" href="{{ route('admin.reports.tickets.csv', $filters) }}">Unduh CSV Lengkap</a>
                <button type="button" class="btn btn-primary" onclick="window.print()">Cetak Ringkasan / Simpan PDF</button>
            </div>
        </div>

        <section class="card report-filter-card">
            <div class="section-heading">
                <div>
                    <div class="eyebrow">FILTER LAPORAN</div>
                    <h2>Tentukan Periode</h2>
                </div>
            </div>
            <form method="get" action="{{ route('admin.reports.tickets.index') }}" class="report-controls"
                id="ticket-report-filter">
                <label for="report-mode">Jenis periode
                    <select id="report-mode" name="mode">
                        <option value="month" @selected($filters['mode'] === 'month')>Per Bulan</option>
                        <option value="custom" @selected($filters['mode'] === 'custom')>Tanggal Custom</option>
                    </select>
                </label>
                <label for="report-month" id="report-month-wrap">Bulan
                    <input id="report-month" type="month" name="month"
                        value="{{ $filters['month'] ?? now('Asia/Jakarta')->format('Y-m') }}" min="2020-01" required>
                </label>
                <div class="report-custom-fields" id="report-custom-fields" @if ($filters['mode'] !== 'custom') hidden @endif>
                    <label for="report-from">Dari tanggal
                        <input id="report-from" type="date" name="from"
                            value="{{ $filters['from'] ?? now('Asia/Jakarta')->startOfMonth()->format('Y-m-d') }}"
                            @if ($filters['mode'] === 'custom') required @endif>
                    </label>
                    <label for="report-to">Sampai tanggal
                        <input id="report-to" type="date" name="to"
                            value="{{ $filters['to'] ?? now('Asia/Jakarta')->format('Y-m-d') }}"
                            @if ($filters['mode'] === 'custom') required @endif>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary report-action">Tampilkan Laporan</button>
            </form>
        </section>

        <section class="report-no-print">
            <div class="report-info">
                <strong>Periode: {{ $periodLabel }}</strong>. Tiket masuk dihitung dari <code>created_at</code> dalam WIB;
                status adalah kondisi tiket saat laporan dibuka, bukan status historis pada akhir periode.
                Indikator <strong>selesai selama periode</strong> menggunakan <code>completed_at</code> dan dapat mencakup
                tiket masuk bulan sebelumnya.
            </div>
        </section>
        <div class="report-print-meta">
            Periode: <strong>{{ $periodLabel }}</strong><br>
            Dicetak: {{ now('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB<br>
            Sumber: TTE-Ticketing (status sesuai kondisi saat laporan dibuat).
        </div>

        <div class="report-metrics">
            <div class="report-metric"><small>Tiket
                    Masuk</small><strong>{{ number_format((int) $summary->total, 0, ',', '.') }}</strong><small>Dalam
                    periode</small></div>
            <div class="report-metric"><small>Belum
                    Diambil</small><strong>{{ number_format((int) $summary->open_count, 0, ',', '.') }}</strong><small>Dari
                    tiket masuk periode</small></div>
            <div class="report-metric"><small>Dalam
                    Proses</small><strong>{{ number_format((int) $summary->progress_count, 0, ',', '.') }}</strong><small>Dari
                    tiket masuk periode</small></div>
            <div class="report-metric"><small>Selesai (Tiket
                    Masuk)</small><strong>{{ number_format((int) $summary->done_count, 0, ',', '.') }}</strong><small>{{ (int) $summary->total > 0 ? number_format((100 * (int) $summary->done_count) / (int) $summary->total, 1, ',', '.') : '0' }}%
                    dari tiket masuk</small></div>
            <div class="report-metric"><small>Selesai Selama
                    Periode</small><strong>{{ number_format($completedDuringPeriod, 0, ',', '.') }}</strong><small>Menurut
                    waktu penyelesaian</small></div>
        </div>

        <section class="card">
            <div class="section-heading">
                <div>
                    <div class="eyebrow">REKAPITULASI</div>
                    <h2>Per Jenis Layanan</h2>
                </div>
            </div>
            <div class="table-scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Layanan</th>
                            <th>Proporsi Tiket Masuk</th>
                            <th class="num">Jumlah</th>
                            <th class="num">Belum Diambil</th>
                            <th class="num">Diproses</th>
                            <th class="num">Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byService as $service)
                            @php($percent = (int) $summary->total > 0 ? (100 * (int) $service->total) / (int) $summary->total : 0)
                            <tr>
                                <td><strong>{{ $service->layanan }}</strong></td>
                                <td>
                                    <div class="report-service-bar"><span style="width:{{ $percent }}%"></span></div>
                                    <span class="small muted">{{ number_format($percent, 1, ',', '.') }}%</span>
                                </td>
                                <td class="num">{{ $service->total }}</td>
                                <td class="num">{{ $service->open_count }}</td>
                                <td class="num">{{ $service->progress_count }}</td>
                                <td class="num">{{ $service->done_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">Tidak ada tiket masuk selama periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="section-heading">
                <div>
                    <div class="eyebrow">KINERJA PETUGAS</div>
                    <h2>Rekap Berdasarkan Verifikator</h2>
                </div>
            </div>
            <p class="small muted">Berdasarkan penanggung jawab saat ini untuk tiket yang masuk dalam periode; termasuk
                verifikator yang akunnya pernah dinonaktifkan.</p>
            <div class="table-scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Nama Verifikator</th>
                            <th class="num">Total Ditangani</th>
                            <th class="num">Diproses</th>
                            <th class="num">Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($verifierStats as $stat)
                            <tr>
                                <td>{{ $verifiers->get($stat->claimed_by)?->name ?? 'Akun tidak tersedia' }}</td>
                                <td class="num">{{ $stat->total }}</td>
                                <td class="num">{{ $stat->progress_count }}</td>
                                <td class="num">{{ $stat->done_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">Belum ada tiket dalam periode yang diambil verifikator.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="section-heading">
                <div>
                    <div class="eyebrow">KANAL MASUK</div>
                    <h2>Sumber Permohonan</h2>
                </div>
            </div>
            <div class="table-scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Sumber</th>
                            <th class="num">Jumlah Tiket</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bySource as $source)
                            <tr>
                                <td>{{ ucfirst($source->source) }}</td>
                                <td class="num">{{ $source->total }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2">Tidak ada data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="small muted">Tiket simulasi/dummy tetap diperhitungkan jika ada pada periode. Periksa sumber data
                sebelum menggunakan hasil untuk laporan resmi.</p>
        </section>

        <section class="card report-detail-card">
            <div class="section-heading">
                <div>
                    <div class="eyebrow">DATA PENDUKUNG</div>
                    <h2>Rincian Tiket Masuk</h2>
                </div>
                <span class="pill pill-neutral">{{ number_format((int) $summary->total, 0, ',', '.') }} tiket</span>
            </div>
            <p class="report-period-note">Menampilkan 20 baris per halaman. Gunakan <strong>Unduh CSV Lengkap</strong> untuk
                seluruh tiket periode. NIK, email, dan nomor WA tidak dimasukkan dalam laporan.</p>
            <div class="table-scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>No. Tiket</th>
                            <th>Masuk (WIB)</th>
                            <th>Pemohon</th>
                            <th>Unit Kerja</th>
                            <th>Layanan</th>
                            <th>Status</th>
                            <th>Verifikator</th>
                            <th>Selesai (WIB)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tickets as $ticket)
                            <tr>
                                <td><strong>{{ $ticket->ticket_number ?? '#' . $ticket->id }}</strong></td>
                                <td>{{ $ticket->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td>
                                <td>{{ $ticket->nama_user }}</td>
                                <td>{{ $ticket->opd ?: '-' }}</td>
                                <td>{{ $ticket->service_label }}</td>
                                <td>{{ $ticket->status_label }}</td>
                                <td>{{ $ticket->claimant?->name ?? 'Belum ditangani' }}</td>
                                <td>{{ $ticket->completed_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">Belum ada tiket untuk periode yang dipilih.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                {{ $tickets->links() }}
            </div>
        </section>
    </div>
    <script>
        (() => {
            const form = document.getElementById('ticket-report-filter');
            if (!form) return;
            const mode = form.querySelector('#report-mode');
            const monthWrap = form.querySelector('#report-month-wrap');
            const month = form.querySelector('#report-month');
            const custom = form.querySelector('#report-custom-fields');
            const from = form.querySelector('#report-from');
            const to = form.querySelector('#report-to');

            function refresh() {
                const isCustom = mode.value === 'custom';
                monthWrap.hidden = isCustom;
                custom.hidden = !isCustom;
                month.disabled = isCustom;
                month.required = !isCustom;
                from.disabled = !isCustom;
                to.disabled = !isCustom;
                from.required = isCustom;
                to.required = isCustom;
            }
            mode.addEventListener('change', refresh);
            refresh();
        })();
    </script>
@endsection
