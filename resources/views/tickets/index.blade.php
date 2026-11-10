@extends ('layouts.app')
@section ('title', 'Antrean Tiket')
@section ('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">WORKSPACE / VERIFIKATOR</div>
            <h1>Antrean Permohonan TTE</h1>
            <p class="muted">Lihat antrean, ambil tiket secara mandiri, dan pantau pekerjaan Anda.</p>
            @if (($stats['duplicate_alerts'] ?? 0) > 0)
                <div class="alert warn" role="status">
                    <strong>{{ $stats['duplicate_alerts'] }} tiket aktif memiliki permohonan berulang.</strong>
                    Periksa penanda pada daftar dan tindak lanjuti tiket sebelumnya.
                </div>
            @endif
        </div>
        <span class="pill pill-blue">● Self-Claim Aktif</span>
    </div>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-icon stat-amber">◷</span
                ><span class="stat-micro">ANTREAN BERSAMA</span>
            </div>
            <div class="stat-name">Belum Diambil</div>
            <div class="stat-number">
                {{
                    number_format(
                        $stats['new'],
                        0,
                        ',',
                        '.',
                    )
                }}
            </div>
            <div class="stat-foot">Tiket menunggu penanganan</div>
        </div>
        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-icon stat-sky">↻</span
                ><span class="stat-micro">PEKERJAAN SAYA</span>
            </div>
            <div class="stat-name">Sedang Dikerjakan</div>
            <div class="stat-number">
                {{
                    number_format(
                        $stats['mine'],
                        0,
                        ',',
                        '.',
                    )
                }}
            </div>
            <div class="stat-foot">Tiket diambil oleh Anda</div>
        </div>
        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-icon stat-emerald">✓</span
                ><span class="stat-micro">HASIL SAYA</span>
            </div>
            <div class="stat-name">Selesai</div>
            <div class="stat-number">
                {{
                    number_format(
                        $stats['completed'],
                        0,
                        ',',
                        '.',
                    )
                }}
            </div>
            <div class="stat-foot">Tiket telah diselesaikan</div>
        </div>
        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-icon stat-amber">↩</span
                ><span class="stat-micro">TIKET SAYA</span>
            </div>
            <div class="stat-name">Perlu Pengajuan Ulang</div>
            <div class="stat-number">
                {{
                    number_format(
                        $stats['returned'],
                        0,
                        ',',
                        '.',
                    )
                }}
            </div>
            <div class="stat-foot">
                <a href="{{ route('tickets.index', ['status' => 'Returned']) }}"
                    >Lihat tiket yang Anda kembalikan →</a
                >
            </div>
        </div>
    </div>
    <nav class="tte-queue-tabs" aria-label="Filter cepat status tiket">
        <a
            href="{{ route('tickets.index', ['status' => 'all']) }}"
            @class (['is-active' => request('status') === 'all'])
            @if (request('status') === 'all') aria-current="page" @endif
            >Semua Tiket</a
        >
        <a
            href="{{ route('tickets.index', ['status' => 'Open']) }}"
            @class (['is-active' => request('status') === 'Open'])
            @if (request('status') === 'Open') aria-current="page" @endif
            >Baru <strong>{{ $stats['new'] }}</strong></a
        >
        <a
            href="{{ route('tickets.index', ['status' => 'In Progress']) }}"
            @class ([
                'is-active' => in_array(request('status'), [null, 'In Progress'], true)
            ])
            @if (in_array(request('status'), [null, 'In Progress'], true)) aria-current="page" @endif
            >Milik Saya <strong>{{ $stats['mine'] }}</strong></a
        >
        <a
            href="{{ route('tickets.index', ['status' => 'Done']) }}"
            @class (['is-active' => request('status') === 'Done'])
            @if (request('status') === 'Done') aria-current="page" @endif
            >Selesai <strong>{{ $stats['completed'] }}</strong></a
        >
        <a
            href="{{ route('tickets.index', ['status' => 'Returned']) }}"
            @class (['is-active' => request('status') === 'Returned'])
            @if (request('status') === 'Returned') aria-current="page" @endif
            >Pengajuan Ulang <strong>{{ $stats['returned'] }}</strong></a
        >
    </nav>
    <section class="card">
        <div class="section-heading">
            <div>
                <div class="eyebrow">MANAJEMEN PERMOHONAN</div>
                <h2>Daftar Tiket dan Antrean</h2>
                <p class="small muted">Antrean milik Anda ditampilkan terlebih dahulu. Pilih Baru untuk tiket yang paling lama menunggu; tab Semua Tiket menampilkan seluruh tiket yang boleh Anda lihat.</p>
            </div>
            <span class="pill pill-neutral">{{ $tickets->total() }} hasil</span>
        </div>
        <form class="filters" method="get" action="{{ route('tickets.index') }}">
            <input
                name="q"
                type="search"
                value="{{ request('q') }}"
                placeholder="Cari nama pemohon / nomor tiket"
                aria-label="Cari tiket" />
            <select name="status" aria-label="Filter status">
                <option value="all" @selected (request('status') === 'all')>Semua Status</option>
                @foreach ([
                        'Open' => 'Baru',
                        'In Progress' => 'Milik Saya',
                        'Done' => 'Selesai',
                        'Returned' => 'Perlu Pengajuan Ulang'
                    ]
                    as $value => $label)
                    <option value="{{ $value }}" @selected (request('status') === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <select name="layanan" aria-label="Filter layanan">
                <option value="">Semua Layanan</option>
                @foreach ([
                        'Reset Passphrase' => 'Reset Passphrase',
                        'Pembaruan' => 'Pembaruan',
                        'Pendaftaran' => 'Pendaftaran'
                    ]
                    as $service => $label)
                    <option value="{{ $service }}" @selected (request('layanan') === $service)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <button class="btn btn-primary" type="submit">Terapkan Filter</button
            ><a href="{{ route('tickets.index') }}" class="btn btn-ghost">Reset</a>
        </form>
        <div class="table-scroll">
            <table class="ticket-table">
                <thead>
                    <tr>
                        <th>Tiket / Tanggal</th>
                        <th>Pemohon / Unit</th>
                        <th>Layanan</th>
                        <th>Hasil AMS</th>
                        <th>Status</th>
                        <th>Verifikator</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td data-label="Nomor Tiket">
                                <strong class="ticket-number">{{
                                    $ticket->ticket_number ??
                                        '#' . $ticket->id
                                }}</strong>
                                <div class="small muted">
                                    {{
                                        $ticket->created_at
                                            ->timezone('Asia/Jakarta')
                                            ->format('d M Y · H:i')
                                    }}
                                </div>
                                @if ($ticket->source === 'simulated')
                                    <span class="demo-label">DATA SIMULASI</span>
                                @endif
                                @if ($ticket->duplicate_attempts > 0 && in_array($ticket->status, ['Open', 'In Progress'], true))
                                    <div class="small" role="status">
                                        <strong>Permohonan berulang: {{ $ticket->duplicate_attempts }} kali</strong>
                                    </div>
                                @endif
                                @if ($ticket->status === 'In Progress' && $ticket->awaiting_applicant_action)
                                    <div class="small">
                                        <strong>Menunggu tindak lanjut pemohon</strong>
                                    </div>
                                @endif
                            </td>
                            <td data-label="Pemohon">
                                <strong>{{ $ticket->nama_user }}</strong>
                                <div class="small muted">
                                    {{
                                        $ticket->opd ?:
                                            'Belum ada unit kerja'
                                    }}
                                </div>
                            </td>
                            <td data-label="Layanan">
                                <span class="service-chip">{{ $ticket->service_label }}</span>
                            </td>
                            <td data-label="Hasil AMS">
                                <span class="small muted">{{ $ticket->ams_summary_label }}</span>
                            </td>
                            <td data-label="Status">
                                <span
                                    class="pill {{ $ticket->status === 'Open' ? 'pill-neutral' : ($ticket->status === 'Done' ? 'pill-green' : ($ticket->status === 'Returned' ? 'tte-pill-returned' : 'pill-blue')) }}">{{ $ticket->status_label }}</span>
                            </td>
                            <td data-label="Verifikator">
                                {{
                                    $ticket->claimant?->name ??
                                        'Belum ada'
                                }}
                            </td>
                            <td data-label="Aksi">
                                @if ($ticket->status === 'Open')
                                    <form
                                        method="post"
                                        action="{{ route('tickets.claim', $ticket) }}">
                                        @csrf
                                        <button class="btn btn-primary btn-small" type="submit">
                                            Ambil Tiket →
                                        </button>
                                    </form>
                                @elseif ($ticket->claimed_by === auth()->id())
                                    <a
                                        class="btn btn-outline btn-small"
                                        href="{{ route('tickets.show', $ticket) }}"
                                        >Lihat Detail →</a
                                    >
                                @else
                                    <span class="small muted">Petugas lain</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty">
                                Tidak ada tiket yang sesuai dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $tickets->links() }}</div>
    </section>
@endsection
