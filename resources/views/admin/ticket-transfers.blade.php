@extends ('layouts.app')
@section ('title', 'Pengalihan Tiket Aktif')
@section ('content')
    <div class="page-heading">
        <div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline heading-back">
                <span aria-hidden="true">←</span> Kembali ke dashboard
            </a>
            <h1>Pengalihan Tiket Aktif</h1>
            <p class="muted">Pilih tiket yang sedang diproses untuk mengalihkan penanganannya ke verifikator lain.</p>
        </div>
        <span class="pill pill-blue">{{ $tickets->total() }} tiket aktif</span>
    </div>
    <section class="card">
        <form method="get" action="{{ route('admin.transfers.index') }}" class="transfer-filters">
            <div class="form-field">
                <label for="transfer-search">Nomor tiket atau nama pemohon</label>
                <input
                    id="transfer-search"
                    name="q"
                    type="search"
                    value="{{ $q }}"
                    maxlength="100" />
            </div>
            <div class="form-field">
                <label for="transfer-owner">Verifikator saat ini</label>
                <select id="transfer-owner" name="owner">
                    <option value="">Semua verifikator</option>
                    @foreach ($verifiers as $verifier)
                        <option
                            value="{{ $verifier->id }}"
                            @selected ((string) request('owner') === (string) $verifier->id)>
                            {{ $verifier->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="transfer-actions">
                <button class="btn btn-outline" type="submit">Cari tiket</button>
                @if ($q !== '' || request()->filled('owner'))
                    <a class="btn btn-ghost" href="{{ route('admin.transfers.index') }}"
                        >Reset filter</a>
                @endif
            </div>
        </form>
    </section>

    @if ($selected)
        @php
            $number = $selected->ticket_number ?: '#' . $selected->id;
            $targets = $verifiers->where('id', '!=', $selected->claimed_by);
            $restore = (int) old('transfer_ticket') === $selected->id;
        @endphp
        <section
            class="card mt"
            id="form-pengalihan"
            aria-labelledby="transfer-heading"
            tabindex="-1">
            <h2 id="transfer-heading">Alihkan tiket {{ $number }}</h2>
            <p class="muted">{{ $selected->nama_user }} · {{ $selected->service_label }} · Verifikator saat ini: <strong>{{
                $selected->claimant?->name ??
                    'Akun tidak aktif'
            }}</strong></p>
            <form
                method="post"
                action="{{ route('admin.transfers.store', ['ticket' => $selected] + $listQuery) }}"
                data-transfer-ticket="{{ $number }}"
                data-owner="{{ $selected->claimant?->name ?? 'Petugas sebelumnya' }}"
                class="stack mt">
                @csrf
                <input type="hidden" name="expected_owner" value="{{ $selected->claimed_by }}" />
                @if ($targets->isNotEmpty())
                    <div class="form-field">
                        <label for="transfer-target">Verifikator tujuan</label>
                        <select id="transfer-target" name="verifier_id" required>
                            <option value="">Pilih verifikator tujuan</option>
                            @foreach ($targets as $verifier)
                                <option
                                    value="{{ $verifier->id }}"
                                    @selected ($restore && old('verifier_id') == $verifier->id)>
                                    {{ $verifier->name }} ({{ $loads[$verifier->id] ?? 0 }} tiket
                                    aktif)
                                </option>
                            @endforeach
                        </select>
                        @error ('verifier_id')
                            <p role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="form-field">
                        <label for="transfer-reason">Alasan pengalihan</label>
                        <textarea
                            id="transfer-reason"
                            name="reason"
                            rows="2"
                            minlength="5"
                            maxlength="500"
                            required
                            >{{
                                $restore && is_string(old('reason'))
                                    ? old('reason')
                                    : ''
                            }}</textarea
                        >
                        @error ('reason')
                            <p role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <p class="small muted">Setelah dialihkan, verifikator tujuan dapat melanjutkan tiket dan petugas lama kehilangan akses penanganan.</p>
                @else
                    <p class="muted">Belum ada verifikator lain yang aktif.</p>
                @endif
                <div class="transfer-actions">
                    @if ($targets->isNotEmpty())
                        <button class="btn btn-primary" type="submit">Simpan pengalihan</button>
                    @endif
                    <a
                        class="btn btn-outline"
                        href="{{ route('admin.transfers.index', $listQuery) }}#tiket-{{ $selected->id }}"
                        >Batal</a>
                </div>
            </form>
        </section>
    @elseif ($selectedId)
        <p class="alert mt" role="status">Tiket yang dipilih sudah tidak tercantum pada halaman ini. Periksa kembali daftar tiket aktif.</p>
    @endif

    <section class="card mt" aria-label="Daftar tiket aktif">
        <div
            class="table-scroll"
            role="region"
            aria-label="Tabel pengalihan tiket aktif"
            tabindex="0">
            <table class="transfer-table" aria-label="Tiket yang sedang diproses">
                <thead>
                    <tr>
                        <th scope="col">Nomor tiket</th>
                        <th scope="col">Pemohon</th>
                        <th scope="col">Layanan</th>
                        <th scope="col">Verifikator saat ini</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="transfer-action-cell">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        @php
                            $ticketNumber = $ticket->ticket_number ?: '#' . $ticket->id;
                            $hasTarget = $verifiers->contains(fn($person) => $person->id !== $ticket->claimed_by);
                        @endphp
                        <tr id="tiket-{{ $ticket->id }}" tabindex="-1">
                            <td>
                                <strong
                                    id="nomor-tiket-{{ $ticket->id }}">{{ $ticketNumber }}</strong>
                            </td>
                            <td>{{ $ticket->nama_user }}</td>
                            <td>{{ $ticket->service_label }}</td>
                            <td>
                                {{
                                    $ticket->claimant?->name ??
                                        'Akun tidak aktif'
                                }}
                            </td>
                            <td><span class="pill pill-blue">{{ $ticket->status_label }}</span></td>
                            <td class="transfer-action-cell">
                                <div class="transfer-actions">
                                    @if ($hasTarget)
                                        <a
                                            class="btn btn-primary btn-small"
                                            href="{{ route('admin.transfers.index', $listQuery + ['alih' => $ticket->id]) }}#form-pengalihan"
                                            aria-label="Alihkan tiket {{ $ticketNumber }}"
                                            >Alihkan</a>
                                    @else
                                        <span class="small muted">Belum ada verifikator pengganti</span>
                                    @endif
                                    <button
                                        type="button"
                                        class="btn btn-outline btn-small"
                                        data-copy-target="nomor-tiket-{{ $ticket->id }}"
                                        aria-label="Salin nomor tiket {{ $ticketNumber }}">
                                        Salin nomor
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty">
                                Tidak ada tiket aktif yang sesuai pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tickets->hasPages())
            <div class="pagination mt">{{ $tickets->links() }}</div>
        @endif
    </section>
@endsection
