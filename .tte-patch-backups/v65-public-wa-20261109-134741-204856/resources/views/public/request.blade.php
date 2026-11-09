@extends('layouts.app')
@section('title', 'Permohonan Layanan TTE')
@section('content')
    <div class="public-request-wrap public-request-v5">
        <header class="public-header" aria-label="Identitas instansi">
            <div class="official-letterhead">
                <div class="official-letterhead__logo">
                    <img src="{{ asset('images/lambang-kabupaten-subang.png') }}" alt="Lambang Kabupaten Subang" width="92"
                        height="108" fetchpriority="high">
                </div>
                <div class="official-letterhead__text">
                    <div class="official-letterhead__government">PEMERINTAH DAERAH KABUPATEN SUBANG</div>
                    <div class="official-letterhead__agency">DINAS KOMUNIKASI DAN INFORMATIKA</div>
                    <div class="official-letterhead__address">Jalan Mayjen Soetoyo Nomor 46, Subang, Jawa Barat 41211</div>
                    <div class="official-letterhead__contact">Telepon (0260) 411318, Faksimile (0260) 411318</div>
                </div>
            </div>
            <div class="official-letterhead__rule" aria-hidden="true"></div>
        </header>

        <div class="public-title-block">
            <h1>Form Permohonan Layanan TTE</h1>
            <p>Lengkapi data berikut untuk mengajukan layanan Tanda Tangan Elektronik.</p>
        </div>

        <div class="public-layout">
            <section class="card public-form-card">
                <form method="post" action="{{ route('public.submit') }}" id="tte-public-form" class="stack"
                    data-lookup-url="{{ route('public.lookup-nik') }}">
                    @csrf
                    <input type="hidden" name="submission_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="signature_data" id="signature_data" value="">
                    <div class="honeypot" aria-hidden="true"><label for="website">Biarkan kosong</label><input
                            id="website" name="website" tabindex="-1" autocomplete="off"></div>

                    <section class="request-step request-entry">
                        <div class="request-step-title"><span class="request-step-number">1</span>
                            <div>
                                <h2>Mulai Permohonan</h2>
                            </div>
                        </div>

                        <div class="request-entry-grid">
                            <div class="field-group">
                                <label for="nik">
                                    <span>
                                        NIK / NIP <span class="required-mark">*</span>
                                    </span>
                                    <input id="nik" name="nik" type="text" inputmode="numeric"
                                        pattern="([0-9]{16}|[0-9]{18})" minlength="16" maxlength="18" required
                                        autocomplete="off" value="{{ old('nik') }}"
                                        placeholder="NIK 16 digit / NIP 18 digit" aria-describedby="nik-help nik-status">
                                </label>

                                <p class="small muted" id="nik-help">NIK 16 digit atau NIP 18 digit, tanpa spasi.</p>

                                <p class="directory-lookup-status small muted" id="nik-status" role="status"
                                    aria-live="polite">

                                </p>
                            </div>

                            <label for="layanan">
                                <span>
                                    Jenis Layanan <span class="required-mark">*</span>
                                </span>
                                <select id="layanan" name="layanan" required>
                                    <option value="">Pilih jenis permohonan</option>
                                    @foreach (\App\Models\WaMessageTemplate::SERVICES as $code => $label)
                                        <option value="{{ $code }}" @selected(old('layanan') === $code)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                    </section>

                    <p id="request-details-pending" class="request-pending-note small muted" role="status">
                        Data pemohon tampil otomatis setelah NIK/NIP ditemukan.
                    </p>

                    <section class="request-step request-details" id="request-details" hidden
                        aria-labelledby="request-details-title">
                        <div class="request-step-title"><span class="request-step-number">2</span>
                            <div>
                                <h2 id="request-details-title">Data Pemohon</h2>
                            </div>
                        </div>
                        <p class="small muted request-inline-note">Data belum sesuai? Anda bisa memperbaikinya. Email Gov
                            mengikuti direktori.</p>
                        <div class="public-fields">
                            <label for="nama_user">Nama Lengkap <span class="required-mark">*</span>
                                <input id="nama_user" name="nama_user" maxlength="160" required
                                    value="{{ old('nama_user') }}" autocomplete="name"
                                    placeholder="Nama lengkap sesuai identitas">
                            </label>
                            <label for="nip">NIP (jika ada)
                                <input id="nip" name="nip" maxlength="32" value="{{ old('nip') }}"
                                    autocomplete="off" placeholder="NIP pegawai">
                            </label>
                            <label for="jabatan">Jabatan <span class="required-mark">*</span>
                                <input id="jabatan" name="jabatan" maxlength="180" required value="{{ old('jabatan') }}"
                                    placeholder="Jabatan saat ini">
                            </label>
                            <label for="opd">Unit Kerja / OPD <span class="required-mark">*</span>
                                <input id="opd" name="opd" maxlength="180" required
                                    value="{{ old('opd') }}" placeholder="Unit kerja saat ini">
                            </label>
                            <div class="field-group" id="uptd-field" hidden>
                                <label for="uptd_display">UPTD
                                    <input id="uptd_display" type="text" value="" readonly aria-readonly="true"
                                        autocomplete="off" aria-describedby="uptd-help">
                                </label>
                                <p class="small muted" id="uptd-help">Sesuai direktori.</p>
                            </div>
                            <div class="field-group request-email-group">
                                <label for="email_display">Email Gov <span class="required-mark">*</span>
                                    <input id="email_display" type="email" value="" readonly
                                        aria-readonly="true" autocomplete="off"
                                        placeholder="Diisi otomatis dari direktori" aria-describedby="email-help">
                                </label>
                                <p class="small muted" id="email-help">Diisi otomatis dari direktori.</p>
                            </div>
                            <div class="field-group wa-phone-field">
                                <label for="no_wa">Nomor WhatsApp aktif <span class="required-mark">*</span></label>
                                <div class="wa-phone-input">
                                    <span class="wa-phone-prefix" aria-hidden="true">
                                        <span class="wa-flag-id"></span>
                                        <span class="wa-phone-code">+62</span>
                                    </span>
                                    <input id="no_wa" name="no_wa" type="tel" inputmode="numeric"
                                        pattern="8[0-9]{8,12}" minlength="9" maxlength="13" required
                                        value="{{ old('no_wa') }}" placeholder="85812341234"
                                        autocomplete="tel-national" aria-describedby="wa-help">
                                </div>
                                <p class="small muted" id="wa-help">Masukkan nomor aktif tanpa angka 0 di depan.</p>
                            </div>
                        </div>
                    </section>

                    <section class="consent-section" aria-labelledby="terms-title">
                        <h2 id="terms-title" class="terms-title">Syarat dan Ketentuan</h2>
                        <div class="terms-divider"></div>
                        <p class="terms-intro">Kami menghargai privasi Anda dan berkomitmen untuk melindungi data pribadi
                            yang Anda berikan kepada kami. <span class="required-mark">*</span></p>
                        <div class="terms-content">
                            Dengan ini, saya menyatakan bahwa data yang saya berikan dalam formulir/dokumen ini adalah
                            benar, lengkap, dan sesuai dengan keadaan yang sebenarnya dan saya memberikan persetujuan kepada
                            <strong>Dinas Komunikasi dan Informatika Kabupaten Subang</strong> untuk mengumpulkan,
                            menyimpan, dan memproses <strong>data pribadi saya</strong> sesuai dengan <strong>kebijakan
                                privasi</strong> dan peraturan perundang-undangan yang berlaku.
                            <p class="terms-notification">Saya juga bersedia menerima pemberitahuan terkait status
                                permohonan layanan TTE melalui nomor WhatsApp yang saya cantumkan.</p>
                        </div>
                        <label class="public-check terms-agreement">
                            <input type="checkbox" name="persetujuan" value="1" required
                                @checked(old('persetujuan'))>
                            <span>Saya setuju dengan Syarat dan Ketentuan yang berlaku.</span>
                        </label>
                    </section>

                    <div class="signature-section">
                        <div class="section-heading">
                            <h2>Tanda Tangan Persetujuan <span class="required-mark">*</span></h2><button type="button"
                                id="signature-clear" class="btn btn-outline btn-small">Bersihkan</button>
                        </div>
                        <canvas id="signature-pad" width="620" height="185"
                            aria-label="Area tanda tangan, gunakan mouse atau sentuhan" role="img"></canvas>
                        <p class="small muted">Tanda tangan persetujuan, bukan TTE tersertifikasi BSrE.</p>
                        <p class="small signature-feedback" id="signature-status" role="status" aria-live="polite">
                            Tanda tangan belum diisi.</p>
                    </div>
                    <p class="request-security-tip">Jangan masukkan password, passphrase, atau OTP pada formulir.</p>
                    <button id="public-submit" class="btn btn-primary full" type="submit" disabled>Kirim Permohonan TTE
                        →</button>
                </form>
            </section>
        </div>
    </div>

    <div class="directory-modal" id="email-help-modal" hidden role="dialog" aria-modal="true"
        aria-labelledby="email-modal-title">
        <div class="directory-modal-backdrop" data-close-directory-modal></div>
        <div class="directory-modal-body" tabindex="-1">
            <div class="directory-modal-icon" aria-hidden="true">!</div>
            <h2 id="email-modal-title">Username Email Belum Tersedia</h2>
            <p>NIK/NIP belum ditemukan atau belum memiliki Email Gov. Hubungi Pengelola Email Dinas untuk bantuan.</p>
            <div class="directory-modal-actions">
                @php($contactUrl = config('email_directory.admin_contact_url'))
                @if (is_string($contactUrl) && str_starts_with($contactUrl, 'https://wa.me/'))
                    <a href="{{ $contactUrl }}" class="btn btn-primary" target="_blank"
                        rel="noopener noreferrer">Hubungi Pengelola via WhatsApp</a>
                @else
                    <span class="small muted">Nomor WhatsApp pengelola email belum dikonfigurasi. Silakan hubungi helpdesk
                        internal.</span>
                @endif
                <button type="button" class="btn btn-outline" id="email-modal-close">Periksa NIK Kembali</button>
            </div>
        </div>
    </div>
    <link rel="stylesheet" href="{{ asset('css/public-request-v5.css') }}?v=23">
    <link rel="stylesheet" href="{{ asset('css/public-request-official.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/wa-phone-prefix.css') }}?v=1">
    <script src="{{ asset('js/public-request.js') }}?v=24" defer></script>
@endsection
