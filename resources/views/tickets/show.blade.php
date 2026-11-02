@extends('layouts.app')
@section('title', 'Detail Tiket')
@section('content')
    <div class="page-heading">
        <div><a href="{{ route('tickets.index') }}" class="breadcrumb-link">← Kembali ke antrean</a>
            <div class="eyebrow mt">DETAIL PERMOHONAN</div>
            <h1>{{ $ticket->ticket_number ?? '#' . $ticket->id }}</h1>
            <p class="muted">{{ $ticket->service_label }}</p>
        </div>
        <div class="heading-right"><span
                class="pill {{ $ticket->status === 'Done' ? 'pill-green' : ($ticket->status === 'Open' ? 'pill-neutral' : 'pill-blue') }}">{{ $ticket->status_label }}</span>
            @if ($ticket->source === 'simulated')
                <span class="demo-label">DATA SIMULASI</span>
            @endif
        </div>
    </div>
    @if ($ticket->source === 'simulated')
        <div class="alert warn" role="status"><strong>Mode simulasi:</strong> Tiket ini menggunakan data dan nomor WhatsApp
            fiktif. Tombol kirim ke WhatsApp dinonaktifkan; gunakan tombol Salin Pesan untuk menguji format pesan.</div>
    @endif
    <div class="columns detail-columns">
        <div class="stack">
            <section class="card">
                <div class="section-heading">
                    <div>
                        <div class="eyebrow">INFORMASI PEMOHON</div>
                        <h2>Detail Permohonan</h2>
                    </div><span class="pill pill-neutral">{{ strtoupper($ticket->source) }}</span>
                </div>
                <div class="detail-grid">
                    <div>
                        <div class="field-name">Nama Pemohon</div>
                        <div class="field-value">{{ $ticket->nama_user }}</div>
                    </div>
                    <div>
                        <div class="field-name">Nomor WhatsApp</div>
                        <div class="field-value">{{ $ticket->no_wa }}</div>
                    </div>
                    <div>
                        <div class="field-name">NIK</div>
                        @if ($ticket->claimed_by !== null && $ticket->claimed_by === auth()->id())
                            <div class="field-value">{{ $ticket->nik ?: '-' }}</div>
                        @else
                            <div class="field-value">{{ $ticket->masked_nik }}</div>
                            @if ($ticket->status === 'Open' && $ticket->nik)
                                <div class="small muted">Ambil tiket untuk melihat NIK lengkap.</div>
                            @endif
                        @endif
                    </div>
                    <div>
                        <div class="field-name">NIP</div>
                        <div class="field-value">{{ $ticket->nip ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="field-name">Unit Kerja / OPD</div>
                        <div class="field-value">{{ $ticket->opd ?: '-' }}</div>
                    </div>
                    @if ($applicantUptd !== '')
                        <div>
                            <div class="field-name">UPTD</div>
                            <div class="field-value">{{ $applicantUptd }}</div>
                        </div>
                    @endif
                    <div>
                        <div class="field-name">Jabatan</div>
                        <div class="field-value">{{ $ticket->jabatan ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="field-name">Email Dinas</div>
                        <div class="field-value">{{ $ticket->email ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="field-name">Tanggal Masuk</div>
                        <div class="field-value">{{ $ticket->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}
                        </div>
                    </div>
                    <div>
                        <div class="field-name">Penanggung Jawab</div>
                        <div class="field-value">{{ $ticket->claimant?->name ?? 'Belum diambil' }}</div>
                    </div>
                </div>
                @if ($ticket->signature_path && $ticket->claimed_by === auth()->id())
                    <div class="note-box mt">
                        <div class="field-name">Tanda tangan persetujuan pemohon</div>
                        <div class="small muted">Persetujuan tercatat:
                            {{ $ticket->consent_accepted_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') ?? '-' }}
                        </div>
                        <img src="{{ route('tickets.signature', $ticket) }}"
                            alt="Tanda tangan persetujuan formulir pemohon" class="signature-evidence" loading="lazy">
                        <div class="small muted">Tanda tangan persetujuan bukan TTE tersertifikasi BSrE dan tidak
                            membuktikan identitas secara mandiri.</div>
                    </div>
                @endif
                @if ($ticket->keterangan)
                    <div class="note-box">
                        <div class="field-name">Keterangan pemohon</div>
                        <div class="pre-wrap">{{ $ticket->keterangan }}</div>
                    </div>
                @endif
            </section>
            <section class="card">
                <div class="section-heading">
                    <div>
                        <div class="eyebrow">JEJAK AKTIVITAS</div>
                        <h2>Riwayat Penanganan</h2>
                    </div><span class="pill pill-neutral">{{ $ticket->activities->count() }} aktivitas</span>
                </div>
                <div class="timeline">
                    @forelse($ticket->activities as $entry)
                        <div class="timeline-item">
                            <div>
                                <strong>{{ match ($entry->action) {
                                    'claimed' => 'Tiket diambil',
                                    'released' => 'Tiket dilepas',
                                    'note' => 'Catatan tindak lanjut',
                                    'completed' => 'Tiket diselesaikan',
                                    'ams_checked' => 'Pemeriksaan status sertifikat dan WA pada AMS BSrE',
                                    'wa_service_confirmed' => 'Pengiriman notifikasi layanan dikonfirmasi',
                                    'wa_confirmed' => 'Notifikasi selesai + survei dikonfirmasi',
                                    default => $entry->action
                                } }}</strong>
                                <div class="small muted">{{ $entry->actor?->name ?? 'Sistem' }} ·
                                    {{ $entry->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</div>
                            </div>
                            @if ($entry->note)
                                <p class="pre-wrap">{{ $entry->note }}</p>
                            @endif
                        </div>
                    @empty<p class="muted">Belum ada aktivitas.</p>
                    @endforelse
                </div>
            </section>
        </div>
        <div class="stack">
            @if ($ticket->status === 'In Progress')
                @php
                    $requiresAmsRecheck = in_array($ticket->layanan, ['Reset Passphrase', 'Pembaruan'], true)
                        && $ticket->certificate_issuance_status === 'not_issued'
                        && $ticket->recommended_service !== 'Pendaftaran';
                    $issuanceNoticeConfirmed = $ticket->activities
                        ->where('action', 'wa_service_confirmed')
                        ->where('note', \App\Models\WaMessageTemplate::EVENTS['certificate_not_issued'])
                        ->isNotEmpty();
                    $onboardingHistory = $ticket->activities
                        ->where('action', 'wa_service_confirmed')
                        ->whereIn('note', [
                            \App\Models\WaMessageTemplate::EVENTS['unregistered'],
                            \App\Models\WaMessageTemplate::EVENTS['certificate_not_issued']
                        ])
                        ->isNotEmpty();
                @endphp
                <section class="card">
                    <div class="section-heading">
                        <div>
                            <div class="eyebrow">LANGKAH 1 / PEMERIKSAAN AMS</div>
                            <h2>Verifikasi Akun TTE dan Nomor WA</h2>
                        </div>
                    </div>
                    <div class="verification-panel">
                        <div class="verification-tag">
                            <strong class="verification-status">
                                {{ $ticket->wa_verification_label }}
                                @if ($ticket->certificate_issuance_status === 'not_issued')
                                    · SE belum terbit
                                @elseif ($ticket->certificate_issuance_status === 'issued')
                                    · SE sudah terbit
                                @elseif ($ticket->activation_status === 'not_activated')
                                    · Akun belum aktivasi
                                @elseif ($ticket->wa_checked_at)
                                    · Status penerbitan SE belum diperiksa
                                @endif
                            </strong>
                            @if ($ticket->wa_checked_at)
                                <div class="small muted">Diperiksa
                                    {{ $ticket->wa_checked_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</div>
                            @endif
                        </div>
                        <p class="small muted">Periksa status nomor WhatsApp pada AMS BSrE. Jika WA sudah
                            terverifikasi, lewati aktivasi akun tetapi <strong>tetap periksa penerbitan SE</strong>.
                            SE bisa belum terbit bila pemohon belum melakukan set passphrase melalui email BSrE.
                            Pilih <strong>Sudah/pernah terbit</strong> bila SE pernah diterbitkan, termasuk bila
                            kedaluwarsa. Pilih <strong>Belum pernah terbit</strong> hanya jika belum ada SE yang pernah diterbitkan.</p>
                        @if ($requiresAmsRecheck)
                            <div class="note-box" role="alert"><strong>Periksa ulang riwayat penerbitan SE.</strong>
                                <p>Hasil lama belum membedakan SE yang belum pernah terbit dan SE yang pernah terbit.
                                    Pilih status yang sesuai lalu simpan hasil pemeriksaan sebelum mengirim notifikasi.</p>
                            </div>
                        @endif
                        <form method="post" action="{{ route('tickets.verify-whatsapp', $ticket) }}" class="stack"
                            id="ams-verification-form" data-requested-service="{{ $ticket->layanan }}"
                            data-onboarding-history="{{ $onboardingHistory ? '1' : '0' }}"
                            data-issuance-notice-confirmed="{{ $issuanceNoticeConfirmed ? '1' : '0' }}">
                            @csrf
                            <label for="ams-wa-status">1. Status verifikasi nomor WhatsApp pada AMS BSrE
                                <select id="ams-wa-status" name="wa_verification_status" required>
                                    <option value="">Pilih status WA</option>
                                    <option value="verified" @selected(old('wa_verification_status', $ticket->wa_verification_status) === 'verified')>Sudah terverifikasi</option>
                                    <option value="unverified" @selected(old('wa_verification_status', $ticket->wa_verification_status) === 'unverified')>Belum terverifikasi</option>
                                </select>
                            </label>
                            <div class="ams-conditional" id="ams-activation-block" hidden>
                                <label for="ams-activation-status">2. Status aktivasi akun pada AMS BSrE
                                    <select id="ams-activation-status" name="activation_status" disabled>
                                        <option value="">Pilih status aktivasi</option>
                                        <option value="activated" @selected(old('activation_status', $ticket->activation_status) === 'activated')>Sudah aktivasi</option>
                                        <option value="not_activated" @selected(old('activation_status', $ticket->activation_status) === 'not_activated')>Belum aktivasi</option>
                                    </select>
                                </label>
                            </div>
                            <div class="ams-conditional" id="ams-certificate-block" hidden>
                                <label for="ams-certificate-status">3. Status penerbitan Sertifikat Elektronik (wajib
                                    diperiksa walaupun WA terverifikasi)
                                    <select id="ams-certificate-status" name="certificate_issuance_status" disabled>
                                        <option value="">Pilih status penerbitan</option>
                                        <option value="issued" @selected(old('certificate_issuance_status', $requiresAmsRecheck ? null : $ticket->certificate_issuance_status) === 'issued')>Sudah/pernah terbit (termasuk kedaluwarsa)</option>
                                        <option value="not_issued" @selected(old('certificate_issuance_status', $requiresAmsRecheck ? null : $ticket->certificate_issuance_status) === 'not_issued')>Belum pernah terbit</option>
                                    </select>
                                </label>
                            </div>
                            <div class="ams-conditional" id="ams-recommendation-block" hidden>
                                <label for="ams-recommended-service">Layanan yang disarankan setelah klarifikasi dengan
                                    pemohon
                                    <select id="ams-recommended-service" name="recommended_service" disabled>
                                        <option value="">{{ $ticket->layanan === 'Pendaftaran' ? 'Pilih layanan yang benar' : 'Layanan sudah sesuai' }}</option>
                                        <option value="Reset Passphrase" @selected(old('recommended_service', $ticket->recommended_service) === 'Reset Passphrase')>Reset Passphrase
                                        </option>
                                        <option value="Pembaruan" @selected(old('recommended_service', $ticket->recommended_service) === 'Pembaruan')>Pembaruan Sertifikat
                                            Elektronik</option>
                                    </select>
                                </label>
                                <p class="small muted">Untuk Reset/Pembaruan, pilih layanan lain hanya setelah klarifikasi. Status AMS saja tidak menentukan kebutuhan pemohon.</p>
                            </div>
                            <div id="ams-decision-preview" class="ams-decision-preview small muted" role="status"
                                aria-live="polite">Lengkapi hasil pemeriksaan AMS.</div>
                            <label for="ams-note">Catatan pemeriksaan (opsional)
                                <textarea id="ams-note" name="wa_verification_note" rows="2" maxlength="500"
                                    placeholder="Catatan hasil pemeriksaan, jangan masukkan OTP atau passphrase">{{ old('wa_verification_note', $ticket->wa_verification_note) }}</textarea>
                            </label>
                            <button type="submit" class="btn btn-primary full">Simpan Hasil Pemeriksaan AMS</button>
                        </form>
                    </div>
                </section>
            @endif

            @if ($serviceEvent === 'ready_for_completion')
                <section class="card">
                    <div class="section-heading">
                        <div>
                            <div class="eyebrow">LANGKAH 2 / HASIL PEMERIKSAAN ULANG</div>
                            <h2>SE Sudah Terbit — Siap Ditandai Selesai</h2>
                        </div>
                    </div>
                    <div class="note-box">
                        <strong>Sertifikat Elektronik sudah terbit.</strong>
                        <p class="small muted">Notifikasi SE Belum Terbit sebelumnya sudah dikonfirmasi.
                            Anda dapat langsung menekan <strong>Tandai Selesai</strong> tanpa mengirim
                            notifikasi Reset, Pembaruan, atau Pendaftaran lagi.
                            Setelah tiket selesai, notifikasi <strong>Selesai + Survei</strong> beserta
                            tautan penilaian akan tersedia untuk dikirim ke pemohon.</p>
                    </div>
                </section>
            @endif

            @if ($serviceMessage && $serviceEvent)
                <section class="card whatsapp-card">
                    <div class="section-heading">
                        <div>
                            <div class="eyebrow">LANGKAH 2 / NOTIFIKASI SESUAI AMS</div>
                            <h2>{{ \App\Models\WaMessageTemplate::EVENTS[$serviceEvent] }}</h2>
                        </div><span class="wa-mark">WA</span>
                    </div>
                    <p class="small muted">Sistem menentukan jenis notifikasi dari status sertifikat, verifikasi WA, dan
                        layanan yang dipilih. Periksa isi pesan sebelum dikirim.</p>
                    @if (in_array($serviceEvent, \App\Models\WaMessageTemplate::CORRECTION_EVENTS, true))
                        <div class="note-box" role="alert"><strong>Permohonan perlu diajukan ulang.</strong>
                            <p>Setelah pesan dikirim, klik <strong>Saya Sudah Mengirim Pesan</strong>.
                                Selanjutnya gunakan tombol <strong>Kembalikan Permohonan</strong> di bagian
                                Penanganan Tiket. Konfirmasi pesan tidak otomatis menutup tiket.
                                Tiket yang dikembalikan berstatus <strong>Perlu Pengajuan Ulang</strong>,
                                bukan dihitung sebagai layanan selesai.
                                Rekomendasi:
                                {{ $ticket->recommended_service === 'Pendaftaran' ? 'Penerbitan Sertifikat Elektronik' : $ticket->recommended_service }}.
                            </p>
                        </div>
                    @elseif ($serviceEvent === 'certificate_not_issued')
                        <div class="note-box"><strong>Menunggu set passphrase.</strong>
                            <p>Pemohon perlu mengikuti petunjuk email BSrE. Pemeriksaan AMS harus diulang sebelum tiket
                                dapat diselesaikan.</p>
                        </div>
                    @endif
                    <div class="message-box">
                        <div class="message-toolbar">
                            <strong>Pratinjau Pesan</strong>
                            <button type="button" class="btn btn-copy btn-small"
                                data-copy-target="service-wa-message">Salin Pesan</button>
                        </div>
                        <div id="service-wa-message" class="message-preview">{{ $serviceMessage }}</div>
                    </div>
                    @unless ($ticket->source === 'simulated')
                        <form target="tte_whatsapp_web" method="post" data-wa-open="1"
                            action="{{ route('tickets.whatsapp.service', $ticket) }}">@csrf
                            <button class="btn btn-wa full" type="submit">Buka WhatsApp Web ↗</button>
                        </form>
                        @if ($serviceNotification?->opened_at)
                            <p class="small muted mt">Tautan dibuka
                                {{ $serviceNotification->opened_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</p>
                            @if ($serviceConfirmed)
                                <div class="alert success mt">Pengiriman notifikasi telah dikonfirmasi oleh petugas.</div>
                            @else
                                <form method="post" action="{{ route('tickets.whatsapp.service.confirm', $ticket) }}"
                                    class="mt">@csrf
                                    <button class="btn btn-outline full" type="submit">Saya Sudah Mengirim Pesan</button>
                                </form>
                            @endif
                        @endif
                    @endunless
                    <p class="small muted mt">Membuka WhatsApp Web bukan bukti pesan terkirim atau diterima. Konfirmasi
                        dilakukan manual.</p>
                </section>
            @endif

            @if ($ticket->status === 'Done' && $waMessage)
                @php($notification = $ticket->whatsappNotifications->firstWhere('event_type', 'completed'))
                <section class="card whatsapp-card">
                    <div class="section-heading">
                        <div>
                            <div class="eyebrow">LANGKAH 3 / NOTIFIKASI AKHIR</div>
                            <h2>Notifikasi Selesai + Survei</h2>
                        </div>
                        <span class="wa-mark">WA</span>
                    </div>
                    <p class="small muted">Kirim notifikasi penutupan tiket yang menyertakan tautan survei kepuasan
                        internal. Tidak ada pengingat inbox tambahan yang wajib.</p>
                    <div class="message-box">
                        <div class="message-toolbar"><strong>Pratinjau Pesan</strong>
                            <button type="button" class="btn btn-copy btn-small"
                                data-copy-target="completed-wa-message">Salin Pesan</button>
                        </div>
                        <div id="completed-wa-message" class="message-preview">{{ $waMessage }}</div>
                    </div>
                    @unless ($ticket->source === 'simulated')
                        <form target="tte_whatsapp_web" method="post" data-wa-open="1" action="{{ route('tickets.whatsapp', $ticket) }}">
                            @csrf
                            <button class="btn btn-wa full" type="submit">Buka WhatsApp Selesai ↗</button>
                        </form>
                        @if ($notification?->opened_at)
                            <p class="small muted mt">Tautan dibuka
                                {{ $notification->opened_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</p>
                            @if ($notification->confirmed_at)
                                <div class="alert success mt">Notifikasi selesai dan tautan survei telah dikonfirmasi oleh
                                    petugas.</div>
                            @else
                                <form method="post" action="{{ route('tickets.whatsapp.confirm', $ticket) }}"
                                    class="mt">@csrf
                                    <button type="submit" class="btn btn-outline full">Saya Sudah Mengirim Pesan</button>
                                </form>
                            @endif
                        @endif
                    @endunless
                </section>
            @endif

            <section class="card">
                <div class="section-heading">
                    <div>
                        <div class="eyebrow">WORKFLOW</div>
                        <h2>Penanganan Tiket</h2>
                    </div>
                    <span class="stat-icon stat-indigo">▤</span>
                </div>
                @if ($ticket->status === 'Open')
                    <p class="muted">Ambil tiket terlebih dahulu untuk melakukan pemeriksaan status pengguna pada AMS
                        BSrE.</p>
                    <form method="post" action="{{ route('tickets.claim', $ticket) }}">@csrf
                        <button class="btn btn-primary full" type="submit">Ambil Tiket Ini →</button>
                    </form>
                @elseif ($ticket->status === 'In Progress')
                    <div class="note-box"><strong>Ditangani oleh {{ $ticket->claimant?->name }}</strong>
                        <p class="small muted">Catatan hanya diperlukan jika ada informasi tambahan. Tidak wajib diisi.</p>
                    </div>
                    <form method="post" action="{{ route('tickets.note', $ticket) }}" class="stack mt">@csrf
                        <label>Catatan tindak lanjut (opsional)
                            <textarea name="note" rows="3" maxlength="3000"
                                placeholder="Catatan pemeriksaan atau tindak lanjut (jika ada)"></textarea>
                        </label>
                        <button class="btn btn-outline full" type="submit">Simpan Catatan</button>
                    </form>
                    <hr>
                    @if (!$serviceEvent)
                        <p class="small muted">Lengkapi pemeriksaan AMS di bagian atas sebelum menyelesaikan tiket.</p>
                    @elseif ($serviceEvent === 'ready_for_completion')
                        <p class="small muted">SE sudah terbit setelah notifikasi SE Belum Terbit dikonfirmasi.
                            Klik Tandai Selesai agar notifikasi Selesai + Survei dan tautan penilaian tersedia.</p>
                    @elseif (in_array($serviceEvent, \App\Models\WaMessageTemplate::CORRECTION_EVENTS, true))
                        @if ($ticket->source === 'simulated')
                            <p class="small muted">Pengembalian melalui WhatsApp tidak tersedia untuk tiket simulasi.</p>
                        @elseif (!$serviceConfirmed)
                            <p class="small muted">Kirim notifikasi Perbaikan Jenis Permohonan, kemudian klik
                                <strong>Saya Sudah Mengirim Pesan</strong> untuk mengaktifkan tombol pengembalian.</p>
                        @else
                            <p class="small muted">Pesan koreksi sudah dikonfirmasi. Klik
                                <strong>Kembalikan Permohonan</strong> untuk menutup tiket secara administratif.
                                Tiket ini tidak menjadi Selesai dan tidak mendapatkan survei.</p>
                        @endif
                    @elseif (!in_array($serviceEvent, ['reset', 'renewal', 'registration'], true))
                        <p class="small muted">Tiket belum dapat diselesaikan. Tindak lanjuti hasil AMS atau arahkan
                            pengajuan ulang terlebih dahulu.</p>
                    @elseif (!$serviceConfirmed && $ticket->source !== 'simulated')
                        <p class="small muted">Notifikasi layanan utama sudah tersedia, sehingga tiket dapat ditandai
                            selesai.
                            Pastikan tindakan layanan telah dilakukan dan kirim notifikasi WhatsApp sebelum menutup tiket.
                            Jika pengiriman belum dikonfirmasi, hal tersebut akan dicatat pada riwayat aktivitas.</p>
                    @else
                        <p class="small muted">Penanganan telah dicatat. Anda dapat menandai tiket selesai tanpa mengisi
                            catatan.</p>
                    @endif
                    @if (in_array($serviceEvent, \App\Models\WaMessageTemplate::CORRECTION_EVENTS, true))
                        <form method="post" action="{{ route('tickets.return-for-correction', $ticket) }}">@csrf
                            <button type="submit" class="btn btn-primary full"
                                @disabled(!$serviceConfirmed || $ticket->source === 'simulated')>Kembalikan Permohonan</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('tickets.complete', $ticket) }}">@csrf
                            <button type="submit" class="btn btn-primary full"
                                @disabled(!in_array($serviceEvent, ['reset', 'renewal', 'registration', 'ready_for_completion'], true))>Tandai Selesai
                                ✓</button>
                        </form>
                    @endif
                    @php($canRelease = !$ticket->activities->whereNotIn('action', ['claimed', 'released'])->count())
                    <form method="post" action="{{ route('tickets.release', $ticket) }}" class="mt">@csrf
                        <button type="submit" class="btn btn-ghost full" @disabled(!$canRelease)>Lepas Tiket (belum
                            dikerjakan)</button>
                    </form>
                    @unless ($canRelease)
                        <p class="small muted mt">Tiket yang sudah diperiksa atau ditindaklanjuti tidak dapat dilepas.</p>
                    @endunless
                @elseif ($ticket->status === 'Returned')
                    <div class="note-box"><strong>Perlu Pengajuan Ulang</strong>
                        <p class="small muted">Pengiriman notifikasi koreksi telah dikonfirmasi, lalu permohonan
                            dikembalikan oleh verifikator. Tiket lama dipertahankan dan tidak dihitung sebagai layanan
                            berhasil maupun dikirimkan survei.</p>
                        <p class="small muted">Layanan diajukan: {{ $ticket->service_label }}. Rekomendasi:
                            {{ $ticket->recommended_service === 'Pendaftaran' ? 'Penerbitan Sertifikat Elektronik' : $ticket->recommended_service }}.
                        </p>
                        <a href="{{ route('public.form') }}" class="btn btn-outline" target="_blank"
                            rel="noopener">Lihat Formulir Pengajuan Baru</a>
                    </div>
                @else
                    <div class="alert success">Tiket selesai pada
                        {{ $ticket->completed_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }}.</div>
                @endif
            </section>
        </div>
    </div>
    <link rel="stylesheet" href="{{ asset('css/ams-workflow.css') }}?v=16">
    <script src="{{ asset('js/ams-workflow.js') }}?v=24" defer></script>
    <script src="{{ asset('js/whatsapp-open-refresh.js') }}?v=1" defer></script>
@endsection
