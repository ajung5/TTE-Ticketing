@extends ('layouts.app')
@section ('title', 'Pengalihan Tiket Aktif')
@section ('content')
    <div class="page-heading">
        <div>
            <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">← Kembali ke dashboard</a>
            <div class="eyebrow mt">ADMIN / PENGENDALIAN PENANGANAN</div>
            <h1>Pengalihan Tiket Aktif</h1>
            <p class="muted">Hanya tiket berstatus Diproses. Admin tidak mengambil alih pemeriksaan AMS atau mengubah hasil pelayanan.</p>
        </div>
        <span class="pill pill-blue">{{ $tickets->total() }} tiket aktif</span>
    </div>
    <section class="card">
        <form method="get" action="{{ route('admin.transfers.index') }}" class="stack">
            <label for="transfer-search">Cari nomor tiket atau nama pemohon</label>
            <input id="transfer-search" name="q" type="search" value="{{ $q }}" maxlength="100" placeholder="Nomor tiket / nama pemohon" />
            <button class="btn btn-outline" type="submit">Cari Tiket</button>
        </form>
    </section>
    <div class="stack mt">
        @forelse ($tickets as $ticket)
            <section class="card">
                <div class="section-heading">
                    <div>
                        <div class="eyebrow">TIKET SEDANG DIPROSES</div>
                        <h2>{{ $ticket->ticket_number ?? '#' . $ticket->id }}</h2>
                    </div>
                    <span class="pill pill-blue">{{ $ticket->status_label }}</span>
                </div>
                <div class="detail-grid">
                    <div><strong>Pemohon</strong><p>{{ $ticket->nama_user }}</p></div>
                    <div><strong>Jenis Layanan</strong><p>{{ $ticket->service_label }}</p></div>
                    <div><strong>Verifikator Sekarang</strong><p>{{ $ticket->claimant?->name ?? 'Akun tidak aktif' }}</p></div>
                </div>
                <form method="post" action="{{ route('admin.transfers.store', $ticket) }}" class="stack mt">
                    @csrf
                    <label for="transfer-target-{{ $ticket->id }}">Alihkan ke verifikator aktif</label>
                    <select id="transfer-target-{{ $ticket->id }}" name="verifier_id" required>
                        <option value="">Pilih verifikator tujuan</option>
                        @foreach ($verifiers as $verifier)
                            @if ($verifier->id !== $ticket->claimed_by)
                                <option value="{{ $verifier->id }}">{{ $verifier->name }}</option>
                            @endif
                        @endforeach
                    </select>
                    <label for="transfer-reason-{{ $ticket->id }}">Alasan pengalihan (wajib)</label>
                    <textarea id="transfer-reason-{{ $ticket->id }}" name="reason" rows="2" minlength="5" maxlength="500" required placeholder="Contoh: Penyesuaian beban kerja, petugas berhalangan, pergantian PIC"></textarea>
                    <button type="submit" class="btn btn-primary">Alihkan Tiket</button>
                    <p class="small muted">Nomor tiket, hasil AMS, dan riwayat tetap tersimpan. Petugas lama kehilangan akses; petugas tujuan langsung dapat melanjutkan.</p>
                </form>
            </section>
        @empty
            <section class="card"><p class="muted">Tidak ada tiket aktif yang sesuai pencarian.</p></section>
        @endforelse
    </div>
    <div class="pagination mt">{{ $tickets->links() }}</div>
@endsection
