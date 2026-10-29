@extends('layouts.app')
@section('title', 'Manajemen Verifikator')
@section('content')
    <style>
        .verifier-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .verifier-form-grid label {
            display: block;
            font-weight: 650;
            font-size: 12px;
            color: #455876;
        }

        .verifier-form-grid input {
            display: block;
            width: 100%;
            margin-top: 7px;
        }

        .verifier-form-grid .full-span {
            grid-column: 1/-1;
        }

        .verifier-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            align-items: center;
        }

        .verifier-edit {
            margin-top: 12px;
            border: 1px solid #dee8f5;
            border-radius: 12px;
            padding: 17px;
            background: #f9fbff;
        }

        .verifier-edit summary {
            cursor: pointer;
            font-weight: 750;
            color: #315b9e;
        }

        .verifier-edit[open] summary {
            margin-bottom: 18px;
        }

        .verifier-edit .btn {
            margin-top: 8px;
        }

        .verifier-danger {
            border: 1px solid #f2cccc;
            color: #a33737;
            background: #fff8f8;
        }

        .verifier-danger:disabled {
            opacity: .45;
            cursor: not-allowed;
        }

        .verifier-help {
            font-size: 12px;
            color: #71809a;
            margin: 8px 0 0;
        }

        .verifier-table td {
            vertical-align: top;
        }

        .verifier-table .person-row {
            min-width: 175px;
        }

        @media(max-width:760px) {
            .verifier-form-grid {
                grid-template-columns: 1fr;
            }

            .verifier-form-grid .full-span {
                grid-column: auto;
            }

            .verifier-edit {
                padding: 13px;
            }

            .verifier-actions {
                align-items: stretch;
            }
        }
    </style>
    <div class="page-heading">
        <div>
            <div class="eyebrow">ADMIN / MANAJEMEN AKUN</div>
            <h1>Manajemen Verifikator</h1>
            <p class="muted">Tambah, ubah profil dan password, serta hapus akun verifikator tanpa menghilangkan riwayat
                penanganan tiket.</p>
        </div>
        <span class="pill pill-blue">{{ $verifiers->count() }} verifikator aktif</span>
    </div>

    <section class="card">
        <div class="section-heading">
            <div>
                <div class="eyebrow">AKUN BARU</div>
                <h2>Tambah Verifikator</h2>
            </div>
        </div>
        <form method="post" action="{{ route('admin.verifiers.store') }}" class="stack" autocomplete="off">
            @csrf
            <div class="verifier-form-grid">
                <label>Nama lengkap <span class="required-mark">*</span>
                    <input name="name" type="text" value="{{ old('name') }}" minlength="3" maxlength="160" required
                        autocomplete="off" placeholder="Nama petugas">
                </label>
                <label>Email akun <span class="required-mark">*</span>
                    <input name="email" type="email" value="{{ old('email') }}" maxlength="255" required
                        autocomplete="off" placeholder="petugas@subang.go.id">
                </label>
                <label>Inisial (opsional)
                    <input name="initials" type="text" value="{{ old('initials') }}" maxlength="12"
                        pattern="[A-Za-z0-9]*" placeholder="Contoh: AWN">
                </label>
                <div></div>
                <label>Password awal <span class="required-mark">*</span>
                    <input name="password" type="password" minlength="12" required autocomplete="new-password"
                        placeholder="Minimal 12 karakter">
                </label>
                <label>Konfirmasi password <span class="required-mark">*</span>
                    <input name="password_confirmation" type="password" minlength="12" required autocomplete="new-password"
                        placeholder="Ulangi password">
                </label>
            </div>
            <p class="verifier-help">Password minimal 12 karakter dan mengandung huruf besar, huruf kecil, angka, serta
                simbol. Akun baru otomatis berperan sebagai verifikator, bukan admin.</p>
            <button class="btn btn-primary" type="submit">Tambah Verifikator</button>
        </form>
    </section>

    <section class="card mt">
        <div class="section-heading">
            <div>
                <div class="eyebrow">DAFTAR AKUN</div>
                <h2>Verifikator Aktif</h2>
            </div>
            <span class="pill pill-neutral">{{ $verifiers->count() }} akun</span>
        </div>
        <p class="small muted">Klik <strong>Edit Akun</strong> untuk memperbarui identitas atau mengganti password. Password
            lama tidak dapat ditampilkan.</p>
        <div class="table-scroll">
            <table class="verifier-table">
                <thead>
                    <tr>
                        <th>Verifikator</th>
                        <th>Inisial</th>
                        <th>Tiket</th>
                        <th>Sedang Diproses</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($verifiers as $verifier)
                        @php($statistics = $counts->get($verifier->id))
                        @php($activeCount = (int) ($statistics?->active_count ?? 0))
                        <tr>
                            <td>
                                <div class="person-row">
                                    <span
                                        class="person-avatar">{{ strtoupper(substr($verifier->initials ?: $verifier->name, 0, 2)) }}</span>
                                    <span><strong>{{ $verifier->name }}</strong><span class="small muted"
                                            style="display:block">{{ $verifier->email }}</span></span>
                                </div>
                            </td>
                            <td>{{ $verifier->initials ?: '-' }}</td>
                            <td>{{ (int) ($statistics?->total ?? 0) }}</td>
                            <td><span class="pill {{ $activeCount ? 'pill-blue' : 'pill-neutral' }}">{{ $activeCount }}
                                    aktif</span></td>
                            <td>
                                <div class="verifier-actions">
                                    <details class="verifier-edit" @if ((string) old('_edit_id') === (string) $verifier->id) open @endif>
                                        <summary>Edit Akun</summary>
                                        <form method="post" action="{{ route('admin.verifiers.update', $verifier) }}"
                                            class="stack" autocomplete="off">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="_edit_id" value="{{ $verifier->id }}">
                                            <div class="verifier-form-grid">
                                                <label>Nama lengkap
                                                    <input type="text" name="name" minlength="3" maxlength="160"
                                                        required
                                                        value="{{ (string) old('_edit_id') === (string) $verifier->id ? old('name', $verifier->name) : $verifier->name }}">
                                                </label>
                                                <label>Email akun
                                                    <input type="email" name="email" maxlength="255" required
                                                        value="{{ (string) old('_edit_id') === (string) $verifier->id ? old('email', $verifier->email) : $verifier->email }}">
                                                </label>
                                                <label>Inisial
                                                    <input type="text" name="initials" maxlength="12"
                                                        pattern="[A-Za-z0-9]*" value="{{ $verifier->initials }}">
                                                </label>
                                            </div>
                                            <p class="small muted">Kosongkan kedua kolom password apabila hanya mengubah
                                                profil.</p>
                                            <div class="verifier-form-grid">
                                                <label>Password baru (opsional)
                                                    <input type="password" name="password" minlength="12"
                                                        autocomplete="new-password"
                                                        placeholder="Biarkan kosong jika tidak diganti">
                                                </label>
                                                <label>Konfirmasi password baru
                                                    <input type="password" name="password_confirmation" minlength="12"
                                                        autocomplete="new-password">
                                                </label>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                        </form>
                                    </details>
                                    <form method="post" action="{{ route('admin.verifiers.destroy', $verifier) }}"
                                        onsubmit="return confirm('Hapus akun verifikator ini? Akun tidak dapat login lagi, tetapi histori tiket tetap tersimpan.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-small verifier-danger"
                                            @disabled($activeCount > 0)
                                            title="{{ $activeCount ? 'Selesaikan tiket aktif terlebih dahulu.' : 'Nonaktifkan akun dan simpan historinya.' }}">Hapus</button>
                                    </form>
                                </div>
                                @if ($activeCount > 0)
                                    <p class="verifier-help">Selesaikan {{ $activeCount }} tiket aktif sebelum menghapus
                                        akun.</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">Belum ada akun verifikator. Tambahkan melalui form di atas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
