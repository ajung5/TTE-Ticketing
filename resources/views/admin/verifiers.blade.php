@extends ('layouts.app')
@section ('title', 'Manajemen Verifikator')
@section ('content')
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
            opacity: 0.45;
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

        @media (max-width: 760px) {
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
            <p class="muted">Tambah, ubah profil dan password, serta hapus akun verifikator tanpa menghilangkan riwayat penanganan tiket.</p>
        </div>
        <span class="pill pill-blue">{{ $verifiers->count() }} verifikator aktif</span>
    </div>

    <details class="card tte-collapsible tte-create-verifier">
        <summary class="section-heading tte-disclosure-heading">
            <div>
                <div class="eyebrow">AKUN BARU</div>
                <h2>Tambah Verifikator</h2>
            </div>
        </summary>
        <form
            method="post"
            action="{{ route('admin.verifiers.store') }}"
            class="stack"
            autocomplete="off">
            @csrf
            <div class="verifier-form-grid">
                <div class="form-field">
                    <label for="create-verifier-name"
                        >Nama lengkap <span class="required-mark">*</span></label>
                    <input
                        id="create-verifier-name"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        minlength="3"
                        maxlength="160"
                        required
                        autocomplete="off"
                        placeholder="Nama petugas" />
                </div>
                <div class="form-field">
                    <label for="create-verifier-email"
                        >Email akun <span class="required-mark">*</span></label>
                    <input
                        id="create-verifier-email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        maxlength="255"
                        required
                        autocomplete="off"
                        placeholder="petugas@subang.go.id" />
                </div>
                <div class="form-field">
                    <label for="create-verifier-initials">Inisial (opsional)</label>
                    <input
                        id="create-verifier-initials"
                        name="initials"
                        type="text"
                        value="{{ old('initials') }}"
                        maxlength="12"
                        pattern="[A-Za-z0-9]*"
                        placeholder="Contoh: AWN" />
                </div>
                <div></div>
                <div class="form-field">
                    <label for="create-verifier-password"
                        >Password awal <span class="required-mark">*</span></label>
                    <input
                        id="create-verifier-password"
                        name="password"
                        type="password"
                        minlength="12"
                        required
                        autocomplete="new-password"
                        placeholder="Minimal 12 karakter" />
                </div>
                <div class="form-field">
                    <label for="create-verifier-password-confirmation"
                        >Konfirmasi password <span class="required-mark">*</span></label>
                    <input
                        id="create-verifier-password-confirmation"
                        name="password_confirmation"
                        type="password"
                        minlength="12"
                        required
                        autocomplete="new-password"
                        placeholder="Ulangi password" />
                </div>
            </div>
            <p class="verifier-help">Password minimal 12 karakter dan mengandung huruf besar, huruf kecil, angka, serta simbol. Akun baru otomatis berperan sebagai verifikator, bukan admin.</p>
            <button class="btn btn-primary" type="submit">Tambah Verifikator</button>
        </form>
    </details>

    <section class="card mt">
        <div class="section-heading">
            <div>
                <div class="eyebrow">DAFTAR AKUN</div>
                <h2>Verifikator Aktif</h2>
            </div>
            <span class="pill pill-neutral">{{ $verifiers->count() }} akun</span>
        </div>
        <p class="small muted">Gunakan <strong>Edit Akun</strong> untuk memperbarui identitas, atau <strong>Kirim Reset Email</strong> agar verifikator mengatur password sendiri. Password lama tidak dapat ditampilkan.</p>
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
                        @php ($statistics = $counts->get($verifier->id))
                        @php ($activeCount = (int) ($statistics?->active_count ?? 0))
                        <tr>
                            <td>
                                <div class="person-row">
                                    <span class="person-avatar">{{
                                        strtoupper(
                                            substr($verifier->initials ?: $verifier->name, 0, 2),
                                        )
                                    }}</span>
                                    <span><strong>{{ $verifier->name }}</strong><span class="small muted" style="display: block;">{{ $verifier->email }}</span></span>
                                </div>
                            </td>
                            <td>{{ $verifier->initials ?: '-' }}</td>
                            <td>{{ (int) ($statistics?->total ?? 0) }}</td>
                            <td>
                                <span
                                    class="pill {{ $activeCount ? 'pill-blue' : 'pill-neutral' }}">{{ $activeCount }} aktif</span>
                            </td>
                            <td>
                                <div class="verifier-actions">
                                    <button
                                        type="button"
                                        class="btn btn-outline btn-small"
                                        data-open-dialog="tte-edit-verifier-{{ $verifier->id }}">
                                        Edit Akun
                                    </button>
                                    <form
                                        method="post"
                                        action="{{ route('admin.password-reset', $verifier) }}"
                                        onsubmit="
                                            return confirm(
                                                'Kirim tautan reset password ke email verifikator ini?',
                                            );
                                        ">
                                        @csrf
                                        <button class="btn btn-outline btn-small" type="submit">
                                            Kirim Reset Email
                                        </button>
                                    </form>
                                    <form
                                        method="post"
                                        action="{{ route('admin.verifiers.destroy', $verifier) }}"
                                        onsubmit="
                                            return confirm(
                                                'Nonaktifkan akun verifikator ini? Akun tidak dapat login lagi, tetapi histori tiket tetap tersimpan.',
                                            );
                                        ">
                                        @csrf
                                        @method ('DELETE')
                                        <button
                                            type="submit"
                                            class="btn btn-small verifier-danger"
                                            @disabled ($activeCount > 0)
                                            title="{{ $activeCount ? 'Selesaikan tiket aktif terlebih dahulu.' : 'Nonaktifkan akun dan simpan historinya.' }}">
                                            Nonaktifkan
                                        </button>
                                    </form>
                                </div>
                                @if ($activeCount > 0)
                                    <p class="verifier-help">Selesaikan {{ $activeCount }} tiket aktif sebelum menonaktifkan akun.</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">
                                Belum ada akun verifikator. Tambahkan melalui form di atas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    {{-- Editor ditempatkan di luar tabel agar tabel tidak melebar saat edit. --}}
    @foreach ($verifiers as $verifier)
        <dialog
            class="tte-verifier-dialog"
            id="tte-edit-verifier-{{ $verifier->id }}"
            aria-labelledby="tte-edit-title-{{ $verifier->id }}"
            @if ((string) old('_edit_id') === (string) $verifier->id) data-auto-open @endif>
            <div class="tte-dialog-heading">
                <div>
                    <h2 id="tte-edit-title-{{ $verifier->id }}">Edit Verifikator</h2>
                    <p class="small muted">{{ $verifier->name }}</p>
                </div>
                <button type="button" class="btn btn-outline btn-small" data-close-dialog>
                    Tutup
                </button>
            </div>
            <form
                method="post"
                action="{{ route('admin.verifiers.update', $verifier) }}"
                class="stack"
                autocomplete="off">
                @csrf
                @method ('PUT')
                <input type="hidden" name="_edit_id" value="{{ $verifier->id }}" />
                <div class="verifier-form-grid">
                    <div class="form-field">
                        <label for="edit-verifier-name-{{ $verifier->id }}">Nama lengkap</label>
                        <input
                            id="edit-verifier-name-{{ $verifier->id }}"
                            type="text"
                            name="name"
                            minlength="3"
                            maxlength="160"
                            required
                            value="{{ (string) old('_edit_id') === (string) $verifier->id ? old('name', $verifier->name) : $verifier->name }}" />
                    </div>
                    <div class="form-field">
                        <label for="edit-verifier-email-{{ $verifier->id }}">Email akun</label>
                        <input
                            id="edit-verifier-email-{{ $verifier->id }}"
                            type="email"
                            name="email"
                            maxlength="255"
                            required
                            value="{{ (string) old('_edit_id') === (string) $verifier->id ? old('email', $verifier->email) : $verifier->email }}" />
                    </div>
                    <div class="form-field">
                        <label for="edit-verifier-initials-{{ $verifier->id }}">Inisial</label>
                        <input
                            id="edit-verifier-initials-{{ $verifier->id }}"
                            type="text"
                            name="initials"
                            maxlength="12"
                            pattern="[A-Za-z0-9]*"
                            value="{{ $verifier->initials }}" />
                    </div>
                </div>
                <p class="small muted">Kosongkan kedua kolom password apabila hanya mengubah profil.</p>
                <div class="verifier-form-grid">
                    <div class="form-field">
                        <label for="edit-verifier-password-{{ $verifier->id }}"
                            >Password baru (opsional)</label>
                        <input
                            id="edit-verifier-password-{{ $verifier->id }}"
                            type="password"
                            name="password"
                            minlength="12"
                            autocomplete="new-password"
                            placeholder="Biarkan kosong jika tidak diganti" />
                    </div>
                    <div class="form-field">
                        <label for="edit-verifier-password-confirmation-{{ $verifier->id }}"
                            >Konfirmasi password baru</label>
                        <input
                            id="edit-verifier-password-confirmation-{{ $verifier->id }}"
                            type="password"
                            name="password_confirmation"
                            minlength="12"
                            autocomplete="new-password" />
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </form>
        </dialog>
    @endforeach
@endsection
