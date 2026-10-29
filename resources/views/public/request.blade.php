@extends('layouts.app')
@section('title', 'Permohonan Layanan TTE')
@section('content')
    <div class="public-request-wrap">
        <header class="public-header">
            <div class="brand-icon">T</div>
            <div>
                <div class="eyebrow">PEMERINTAH KABUPATEN SUBANG</div>
                <h1>Permohonan Layanan TTE</h1>
                <p class="muted">Ajukan layanan sertifikat elektronik BSrE tanpa login.</p>
            </div>
        </header>
        <div class="public-layout">
            <section class="card public-form-card">
                <div class="section-heading">
                    <div>
                        <h2>Formulir Permohonan</h2>
                        <p class="small muted">Kolom bertanda * wajib diisi. Data email dicocokkan dengan direktori resmi.
                        </p>
                    </div><span class="pill pill-blue">Layanan Online</span>
                </div>
                <form method="post" action="{{ route('public.submit') }}" id="tte-public-form" class="stack"
                    data-lookup-url="{{ route('public.lookup-nik') }}">
                    @csrf
                    <input type="hidden" name="submission_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="signature_data" id="signature_data" value="">
                    <div class="honeypot" aria-hidden="true"><label for="website">Biarkan kosong</label><input
                            id="website" name="website" tabindex="-1" autocomplete="off"></div>
                    <label for="layanan">Jenis Layanan *
                        <select id="layanan" name="layanan" required>
                            <option value="">Pilih jenis permohonan</option>
                            @foreach (\App\Models\WaMessageTemplate::SERVICES as $code => $label)
                                <option value="{{ $code }}" @selected(old('layanan') === $code)>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <div class="public-fields">
                        <label for="nama_user">Nama Lengkap *<input id="nama_user" name="nama_user" maxlength="160" required
                                value="{{ old('nama_user') }}" autocomplete="name"
                                placeholder="Nama pemohon sesuai identitas"></label>
                        <div class="field-group">
                            <label for="nik">NIK (16 digit) *<input id="nik" name="nik" type="text"
                                    inputmode="numeric" pattern="[0-9]{16}" minlength="16" maxlength="16" required
                                    autocomplete="off" placeholder="Masukkan 16 digit NIK"
                                    aria-describedby="nik-help nik-status"></label>
                            <div class="small muted" id="nik-help">Email dinas akan dicocokkan otomatis berdasarkan NIK.
                            </div>
                            <div class="directory-lookup-status small muted" id="nik-status" role="status"
                                aria-live="polite">Belum diperiksa.</div>
                        </div>
                        <label for="nip">NIP (jika ada)<input id="nip" name="nip" maxlength="32"
                                value="{{ old('nip') }}" autocomplete="off" placeholder="NIP pegawai"></label>
                        <div class="field-group">
                            <label for="email_display">Email Dinas (otomatis) *<input id="email_display" type="text"
                                    value="" readonly autocomplete="off" placeholder="Masukkan NIK terlebih dahulu"
                                    aria-describedby="email-help"></label>
                            <div class="small muted" id="email-help">Ditampilkan tersamarkan. Email lengkap diambil langsung
                                oleh server dari direktori, bukan dari browser.</div>
                        </div>
                        <label for="no_wa">Nomor WhatsApp *<input id="no_wa" name="no_wa" type="tel"
                                maxlength="30" required value="{{ old('no_wa') }}" placeholder="081234567890"
                                autocomplete="tel"></label>
                        <label for="opd">Unit Kerja / OPD *<input id="opd" name="opd" maxlength="180"
                                required value="{{ old('opd') }}" placeholder="Nama perangkat daerah"></label>
                        <label for="jabatan">Jabatan *<input id="jabatan" name="jabatan" maxlength="180" required
                                value="{{ old('jabatan') }}" placeholder="Jabatan saat ini"></label>
                    </div>
                    <label for="keterangan">Keterangan Tambahan (opsional)
                        <textarea name="keterangan" id="keterangan" rows="3" maxlength="2000"
                            placeholder="Jelaskan kebutuhan atau kendala. Jangan masukkan OTP, password, atau passphrase.">{{ old('keterangan') }}</textarea>
                    </label>
                    <div class="consent-section">
                        <h2>Persetujuan Pemrosesan Data</h2>
                        <p class="small muted">Data identitas, kontak, dan tanda tangan persetujuan akan diproses untuk
                            layanan sertifikat elektronik, pencatatan tiket, audit, dan pemberitahuan status. Pengecekan
                            username email bukan verifikasi identitas pemohon.</p>
                        <label class="public-check"><input type="checkbox" name="persetujuan" value="1" required
                                @checked(old('persetujuan'))><span>Saya menyetujui pemrosesan data di atas dan pemberitahuan
                                layanan melalui WhatsApp. *</span></label>
                    </div>
                    <div class="signature-section">
                        <div class="section-heading">
                            <h2>Tanda Tangan Persetujuan *</h2><button type="button" id="signature-clear"
                                class="btn btn-outline btn-small">Bersihkan</button>
                        </div>
                        <canvas id="signature-pad" width="620" height="185"
                            aria-label="Area tanda tangan digital, gunakan mouse atau sentuhan" role="img"></canvas>
                        <div class="small muted">Tanda tangani dengan mouse atau jari. Ini bukti persetujuan formulir,
                            bukan TTE tersertifikasi BSrE.</div>
                        <div class="small signature-feedback" id="signature-status" role="status" aria-live="polite">
                            Tanda tangan belum diisi.</div>
                    </div>
                    <div class="note-box"><strong>Keamanan informasi</strong>
                        <p class="small muted">Jangan memasukkan passphrase, password AMS, OTP, atau mengunggah KTP. NIK
                            disimpan terenkripsi dan email tidak dibuka secara lengkap pada halaman publik.</p>
                    </div>
                    <button id="public-submit" class="btn btn-primary full" type="submit" disabled>Kirim Permohonan TTE
                        →</button>
                </form>
            </section>
            <aside class="stack public-aside">
                <section class="card">
                    <div class="eyebrow">ALUR LAYANAN</div>
                    <h2>Setelah Formulir Dikirim</h2>
                    <ol class="public-steps">
                        <li>Masukkan NIK, sistem mencocokkan email dinas.</li>
                        <li>Lengkapi data, persetujuan, dan tanda tangan.</li>
                        <li>Permohonan menerima nomor tiket.</li>
                        <li>Verifikator memeriksa identitas dan nomor WhatsApp pada AMS BSrE.</li>
                    </ol>
                </section>
            </aside>
        </div>
    </div>
    <div class="directory-modal" id="email-help-modal" hidden role="dialog" aria-modal="true"
        aria-labelledby="email-modal-title">
        <div class="directory-modal-backdrop" data-close-directory-modal></div>
        <div class="directory-modal-body" tabindex="-1">
            <div class="directory-modal-icon">!</div>
            <h2 id="email-modal-title">Username Email Belum Tersedia</h2>
            <p>NIK yang dimasukkan belum ditemukan atau belum memiliki username Email Gov pada direktori. Silakan
                menghubungi Pengelola Email Dinas Kabupaten Subang sebelum mengirim permohonan.</p>
            <div class="directory-modal-actions">
                @php($contactUrl = config('email_directory.admin_contact_url'))
                @if (is_string($contactUrl) && preg_match('/^(https:\/\/|mailto:)/i', $contactUrl))
                    <a href="{{ $contactUrl }}" class="btn btn-primary" target="_blank"
                        rel="noopener noreferrer">Hubungi Pengelola Email</a>
                @else
                    <span class="small muted">Kontak resmi pengelola email belum dikonfigurasi. Silakan hubungi helpdesk
                        internal.</span>
                @endif
                <button type="button" class="btn btn-outline" id="email-modal-close">Perbaiki NIK</button>
            </div>
        </div>
    </div>
    <script src="{{ asset('js/public-request.js') }}?v=4" defer></script>
@endsection
