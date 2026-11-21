@extends ('layouts.app')
@section ('title', 'Detail Tiket')
@section ('content')
    <div class="page-heading">
        <div>
            <a
                href="{{ route('tickets.index', $queueContext) }}#ticket-{{ $ticket->id }}"
                class="breadcrumb-link"
                >← Kembali ke antrean</a>
            <div class="eyebrow mt">DETAIL PERMOHONAN</div>
            <h1>
                {{
                    $ticket->ticket_number ??
                        '#' . $ticket->id
                }}
            </h1>
            <p class="muted">Layanan diajukan: {{ $ticket->service_label }}</p>
        </div>
        <div class="heading-right">
            <span
                class="pill {{ $ticket->status === 'Done' ? 'pill-green' : ($ticket->status === 'Open' ? 'pill-neutral' : ($ticket->status === 'Returned' ? 'tte-pill-returned' : 'pill-blue')) }}">{{ $ticket->status_label }}</span>
            @if ($ticket->source === 'simulated')
                <span class="demo-label">DATA SIMULASI</span>
            @endif
        </div>
    </div>
    <div class="tte-ticket-context" aria-label="Ringkasan tiket">
        <span><strong>Pemohon</strong> {{ $ticket->nama_user }}</span>
        <span><strong>Unit Kerja</strong> {{ $ticket->opd ?: '-' }}</span>
        <span><strong>Verifikator</strong> {{
            $ticket->claimant?->name ??
                'Belum diambil'
        }}</span>
    </div>
    @php
        $latestTransfer = $ticket->activities->where('action', 'admin_reassigned')->last();
    @endphp
    @if ($latestTransfer)
        <div class="alert warn" role="status">
            <strong>Tiket pernah dialihkan oleh admin.</strong>
            {{ $latestTransfer->note }}
        </div>
    @endif
    @if ($ticket->duplicate_attempts > 0 && in_array($ticket->status, ['Open', 'In Progress'], true))
        <div class="alert warn" role="status">
            <strong>Permohonan berulang telah ditahan: {{ $ticket->duplicate_attempts }} kali.</strong>
            @if ($ticket->last_duplicate_attempt_at)
                Terakhir {{
                    $ticket->last_duplicate_attempt_at
                        ->timezone('Asia/Jakarta')
                        ->format('d M Y H:i')
                }}.
            @endif
            @if ($ticket->awaiting_applicant_action)
                Pemohon perlu menindaklanjuti instruksi yang telah disampaikan melalui WhatsApp.
            @else
                Pastikan progres permohonan sebelumnya sebelum mengarahkan pemohon.
            @endif
            Jangan membuat tiket kedua untuk kasus yang sama.
        </div>
    @elseif ($ticket->status === 'In Progress' && $ticket->awaiting_applicant_action)
        <div class="alert warn" role="status">
            Menunggu tindak lanjut pemohon sesuai notifikasi layanan terakhir.
        </div>
    @endif
    @if ($ticket->source === 'simulated')
        <div class="alert warn" role="status">
            <strong>Mode simulasi:</strong> Tiket ini menggunakan data dan nomor WhatsApp fiktif.
            Tombol kirim ke WhatsApp dinonaktifkan; gunakan tombol Salin Pesan untuk menguji format
            pesan.
        </div>
    @endif
    <section class="tte-next-action" aria-label="Tindakan berikutnya">
        <div class="tte-next-eyebrow">
            Tindakan Berikutnya · Tahap {{ $nextAction['step'] }} dari 4
        </div>
        <ol class="tte-next-progress" aria-label="Progres penanganan">
            @for ($step = 1; $step <= 4; $step++)
                <li
                    @class ([
                        'is-complete' => $step < $nextAction['step'],
                        'is-current' => $step === $nextAction['step']
                    ])
                    aria-label="Tahap {{ $step }}{{ $step < $nextAction['step'] ? ' selesai' : ($step === $nextAction['step'] ? ' saat ini' : ' berikutnya') }}"></li>
            @endfor
        </ol>
        <h2>{{ $nextAction['title'] }}</h2>
        <p>{{ $nextAction['description'] }}</p>
        @if ($nextAction['target'])
            <a class="btn btn-primary" href="{{ $nextAction['target'] }}"
                >{{ $nextAction['button'] }} →</a>
        @endif
    </section>
    @if ($ticket->status === 'In Progress')
        <section class="tte-ux-ticket-ams" aria-labelledby="tte-ux-ams-heading">
            <h2 id="tte-ux-ams-heading">Ringkasan Pemeriksaan AMS</h2>
            <p class="small muted">Ringkasan hasil pemeriksaan terakhir, bukan pembacaan real-time AMS BSrE.</p>
            <div class="tte-ux-ams-grid">
                <div class="tte-ux-ams-item">
                    <span>Aktivasi akun</span><strong>{{ $ticket->activation_label }}</strong>
                </div>
                <div class="tte-ux-ams-item">
                    <span>Verifikasi WhatsApp</span><strong>{{ $ticket->wa_verification_label }}</strong>
                </div>
                <div class="tte-ux-ams-item">
                    <span>Penerbitan dan status SE</span><strong>{{ $ticket->certificate_status_label }}</strong>
                </div>
                <div class="tte-ux-ams-item">
                    <span>Status tiket</span><strong>{{ $ticket->status_label }}</strong>
                </div>
            </div>
            @if (!$completionReady)
                <p class="tte-ux-ams-note">Ikuti <strong>Tindakan Berikutnya</strong> dan periksa hasil AMS serta konfirmasi WhatsApp sesuai proses yang berlaku sebelum menutup tiket.</p>
            @endif
        </section>
    @endif
    <div class="columns detail-columns">
        <div class="stack">
            <section class="card">
                <div class="section-heading">
                    <div>
                        <div class="eyebrow">INFORMASI PEMOHON</div>
                        <h2>Detail Permohonan</h2>
                    </div>
                    <span class="pill pill-neutral">{{ strtoupper($ticket->source) }}</span>
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
                                <div class="small muted">
                                    Ambil tiket untuk melihat NIK lengkap.
                                </div>
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
                        <div class="field-name">Email Dinas (digunakan pada tiket)</div>
                        <div class="field-value">{{ $ticket->email ?: '-' }}</div>
                        @if ($ticket->source === 'direct' && $ticket->directory_email)
                            <div class="small muted">
                                Sesuai direktori saat pengajuan: {{ $ticket->directory_email }}
                            </div>
                        @endif
                        @if ($ticket->email_corrected_at)
                            <div class="small muted">
                                Dikoreksi berdasarkan AMS: {{
                                    $ticket->email_corrected_at
                                        ->timezone('Asia/Jakarta')
                                        ->format('d M Y H:i')
                                }}
                            </div>
                        @endif
                    </div>
                    <div>
                        <div class="field-name">Tanggal Masuk</div>
                        <div class="field-value">
                            {{
                                $ticket->created_at
                                    ->timezone('Asia/Jakarta')
                                    ->format('d M Y, H:i')
                            }}
                        </div>
                    </div>
                    <div>
                        <div class="field-name">Penanggung Jawab</div>
                        <div class="field-value">
                            {{
                                $ticket->claimant?->name ??
                                    'Belum diambil'
                            }}
                        </div>
                    </div>
                </div>
                @if ($ticket->status === 'In Progress' &&
                    $ticket->claimed_by === auth()->id() &&
                    $ticket->source !== 'simulated')
                    <details class="note-box mt">
                        <summary><strong>Koreksi Email berdasarkan AMS BSrE</strong></summary>
                        <p class="small muted">Gunakan hanya apabila email pada tiket berbeda dengan email yang telah Anda periksa di AMS. Perubahan ini tidak mengubah direktori Email Gov.</p>
                        <form
                            method="post"
                            action="{{ route('tickets.email.update', $ticket) }}"
                            class="stack mt">
                            @csrf
                            @method ('PATCH')
                            <label>Email yang terdaftar di AMS BSrE
                                <input
                                    type="email"
                                    name="email"
                                    maxlength="180"
                                    required
                                    value="{{ old('email', $ticket->email) }}"
                                    autocomplete="off"
                                    placeholder="nama@subang.go.id" />
                            </label>
                            @error ('email')
                                <p class="small" role="alert">{{ $message }}</p>
                            @enderror
                            <label>Alasan koreksi
                                <textarea
                                    name="reason"
                                    rows="2"
                                    minlength="10"
                                    maxlength="500"
                                    required
                                    placeholder="Jelaskan ketidaksesuaian yang ditemukan"
                                    >{{ old('reason') }}</textarea
                                >
                            </label>
                            @error ('reason')
                                <p class="small" role="alert">{{ $message }}</p>
                            @enderror
                            <label><input type="checkbox" name="ams_confirmed" value="1" required />
                                Saya telah memeriksa alamat email ini langsung di AMS BSrE.</label>
                            @error ('ams_confirmed')
                                <p class="small" role="alert">{{ $message }}</p>
                            @enderror
                            <button class="btn btn-outline" type="submit">
                                Simpan Koreksi Email
                            </button>
                        </form>
                    </details>
                @endif
                <div class="note-box mt">
                    <strong>Pemeriksaan dan Hasil Pelayanan</strong>
                    <div class="detail-grid mt">
                        <div>
                            <div class="field-name">Layanan Diajukan</div>
                            <div class="field-value">{{ $ticket->service_label }}</div>
                        </div>
                        <div>
                            <div class="field-name">Kesesuaian</div>
                            <div class="field-value">{{ $ticket->service_alignment_label }}</div>
                        </div>
                        <div>
                            <div class="field-name">Proses Aktual</div>
                            <div class="field-value">{{ $ticket->actual_process_label }}</div>
                        </div>
                        <div>
                            <div class="field-name">Hasil Akhir</div>
                            <div class="field-value">
                                {{
                                    $ticket->status === 'Done'
                                        ? $ticket->service_outcome_label
                                        : 'Belum selesai'
                                }}
                            </div>
                        </div>
                    </div>
                    <p class="small muted mt">Layanan awal tidak ditimpa. Proses dan hasil sebenarnya dicatat terpisah demi ketepatan notifikasi serta laporan.</p>
                </div>
                @if ($ticket->signature_path && $ticket->claimed_by === auth()->id())
                    <div class="note-box mt">
                        <div class="field-name">Tanda tangan persetujuan pemohon</div>
                        <div class="small muted">
                            Persetujuan tercatat: {{
                                $ticket->consent_accepted_at
                                    ?->timezone('Asia/Jakarta')
                                    ->format('d M Y, H:i') ?? '-'
                            }}
                        </div>
                        <img
                            src="{{ route('tickets.signature', $ticket) }}"
                            alt="Tanda tangan persetujuan formulir pemohon"
                            class="signature-evidence"
                            loading="lazy" />
                        <div class="small muted">
                            Tanda tangan persetujuan bukan TTE tersertifikasi BSrE dan tidak
                            membuktikan identitas secara mandiri.
                        </div>
                    </div>
                @endif
                @if ($ticket->keterangan)
                    <div class="note-box">
                        <div class="field-name">Keterangan pemohon</div>
                        <div class="pre-wrap">{{ $ticket->keterangan }}</div>
                    </div>
                @endif
            </section>
            <details class="card tte-history-details">
                <summary class="section-heading tte-disclosure-heading">
                    <div>
                        <div class="eyebrow">JEJAK AKTIVITAS</div>
                        <h2>Riwayat Penanganan</h2>
                    </div>
                    <span
                        class="pill pill-neutral">{{ $ticket->activities->count() }} aktivitas</span>
                </summary>
                <div class="timeline">
                    @forelse ($ticket->activities as $entry)
                        <div class="timeline-item">
                            <div>
                                <strong>{{
                                    match ($entry->action) {
                                        'claimed' => 'Tiket diambil',
                                        'released' => 'Tiket dilepas',
                                        'note' => 'Catatan tindak lanjut',
                                        'completed' => 'Tiket diselesaikan',
                                        'ams_checked' => 'Pemeriksaan akun, WA, penerbitan, dan status SE di AMS',
                                        'duplicate_request_blocked' => 'Permohonan berulang ditahan',
                                        'email_corrected_from_ams' => 'Koreksi email berdasarkan AMS',
                                        'wa_service_confirmed' => 'Pengiriman notifikasi layanan dikonfirmasi',
                                        'wa_confirmed' => 'Notifikasi selesai + survei dikonfirmasi',
                                        'admin_reassigned' => 'Admin mengalihkan penanggung jawab tiket',
                                        'ams_link_sent' => 'Pengiriman tautan AMS dicatat',
                                        'ams_link_wa_confirmed' => 'Pemberitahuan pengiriman tautan dikonfirmasi',
                                        'reset_link_resent' => 'Tautan Reset Passphrase dikirim ulang melalui AMS',
                                        'reset_resend_wa_confirmed' => 'Notifikasi kirim ulang Reset Passphrase dikonfirmasi',
                                        default => $entry->action,
                                    }
                                }}</strong>
                                <div class="small muted">
                                    {{ $entry->actor?->name ?? 'Sistem' }} · {{
                                        $entry->created_at
                                            ->timezone('Asia/Jakarta')
                                            ->format('d M Y H:i')
                                    }}
                                </div>
                            </div>
                            @if ($entry->note)
                                <p class="pre-wrap">{{ $entry->note }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="muted">Belum ada aktivitas.</p>
                    @endforelse
                </div>
            </details>
        </div>
        <div class="stack">
            @if ($ticket->status === 'In Progress')
                @php
                    $issuanceNoticeConfirmed = $ticket->activities
                        ->where('action', 'wa_service_confirmed')
                        ->whereIn('note', [
                            \App\Models\WaMessageTemplate::EVENTS['certificate_not_issued'],
                            \App\Models\WaMessageTemplate::EVENTS['certificate_revoked'],
                            \App\Models\WaMessageTemplate::EVENTS['renewal'],
                        ])
                        ->isNotEmpty();
                @endphp
                <section class="card" id="tte-ams-check">
                    <div class="section-heading">
                        <div>
                            <div class="eyebrow">LANGKAH 1 / PEMERIKSAAN AMS</div>
                            <h2>Pemeriksaan Akun, WhatsApp, Sertifikat, dan Layanan</h2>
                        </div>
                    </div>
                    <div
                        class="tte-ams-requested-service"
                        role="note"
                        aria-label="Layanan diajukan pemohon">
                        <div>
                            <span
                                class="tte-ams-requested-label">LAYANAN DIAJUKAN OLEH PEMOHON</span>
                            <strong
                                class="tte-ams-requested-name">{{ $ticket->service_label }}</strong>
                        </div>
                        <p>Bandingkan pilihan layanan pemohon dengan hasil pemeriksaan AMS. Jenis layanan yang diajukan tidak berubah otomatis.</p>
                    </div>
                    <div class="verification-panel">
                        <div class="verification-tag">
                            <strong class="verification-status">
                                Akun: {{ $ticket->activation_label }} · WA: {{ $ticket->wa_verification_label }} · {{ $ticket->certificate_status_label }}
                            </strong>
                            @if ($ticket->wa_checked_at)
                                <div class="small muted">
                                    Pemeriksaan terakhir: {{
                                        $ticket->wa_checked_at
                                            ->timezone('Asia/Jakarta')
                                            ->format('d M Y H:i')
                                    }}
                                </div>
                            @endif
                        </div>
                        <details class="tte-ams-help">
                            <summary class="small muted">Panduan urutan pemeriksaan AMS</summary>
                            <p class="small muted">Urutan pemeriksaan pada <strong>AMS BSrE</strong>: <strong>1. Aktivasi akun</strong>, <strong>2. Verifikasi WhatsApp</strong>, <strong>3. Penerbitan Sertifikat Elektronik</strong>, <strong>4. Status SE (Aktif, Expired, Revoke)</strong>, dan <strong>5. Kesesuaian pilihan layanan</strong>. Status sudah terbit tidak otomatis berarti sertifikat aktif.</p>
                        </details>
                        <form
                            method="post"
                            action="{{ route('tickets.verify-whatsapp', $ticket) }}"
                            class="stack"
                            id="ams-verification-form"
                            data-requested-service="{{ $ticket->layanan }}"
                            data-continuation-mismatch="{{ $continuationMismatch ? '1' : '0' }}"
                            data-onboarding-history="{{ $onboardingHistory ? '1' : '0' }}"
                            data-issuance-notice-confirmed="{{ $issuanceNoticeConfirmed ? '1' : '0' }}">
                            @csrf
                            @include ('tickets.ams-fields')
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
                        <strong>Sertifikat Elektronik telah dikonfirmasi aktif.</strong>
                        <p class="small muted">Notifikasi tindak lanjut sebelumnya sudah dikonfirmasi. Anda dapat menekan <strong>Tandai Selesai</strong> tanpa mengirim notifikasi antara lagi. Hasil akhir akan mengikuti proses aktual, bukan otomatis nama layanan awal. Setelah selesai, notifikasi <strong>Selesai + Survei</strong> tersedia.</p>
                    </div>
                </section>
            @endif

            @if ($serviceMessage && $serviceEvent)
                <section class="card whatsapp-card" id="tte-service-notification">
                    <div class="section-heading">
                        <div>
                            <div class="eyebrow">LANGKAH 2 / NOTIFIKASI SESUAI AMS</div>
                            <h2>
                                {{
                                    \App\Models\WaMessageTemplate::EVENTS[
                                        $serviceEvent
                                    ]
                                }}
                            </h2>
                        </div>
                        <span class="wa-mark">WA</span>
                    </div>
                    <p class="small muted">Notifikasi mengikuti kondisi aktual AMS dan tahap pelayanan. Periksa isi pesan sebelum dikirim.</p>
                    @if (in_array( $serviceEvent, \App\Models\WaMessageTemplate::CORRECTION_EVENTS, true ))
                        <div class="note-box" role="alert">
                            <strong>Permohonan perlu diajukan ulang.</strong>
                            <p>Setelah pesan dikirim, klik <strong>Saya Sudah Mengirim Pesan</strong>, lalu <strong>Kembalikan Permohonan</strong>. Tiket lama dipertahankan berstatus <strong>Perlu Pengajuan Ulang</strong>; tidak dihitung selesai. Rekomendasi: {{
                                $ticket->recommended_service === 'Pendaftaran'
                                    ? 'Penerbitan Sertifikat Elektronik'
                                    : $ticket->recommended_service
                            }}.</p>
                        </div>
                    @elseif ($serviceEvent === 'registration' && $ticket->layanan === 'Pendaftaran')
                        <div class="note-box">
                            <strong>Pendaftaran Diproses — penutupan administratif.</strong>
                            <p>
                                Kirim Notifikasi 4 melalui WhatsApp. Setelah benar-benar mengirim
                                pesan, klik
                                <strong>Konfirmasi WA &amp; Selesaikan Administrasi</strong>. Tiket
                                otomatis menjadi <strong>Selesai</strong>, lalu tersedia Notifikasi
                                Selesai + Survei.
                                @if ($ticket->activation_status === 'not_activated')
                                    Pemohon masih perlu mengaktifkan akun sesuai petunjuk BSrE.
                                @endif
                                Status Selesai berarti
                                <strong>penanganan pengajuan oleh verifikator selesai</strong>,
                                bukan menyatakan SE sudah terbit.
                            </p>
                        </div>
                    @elseif ($serviceEvent === 'certificate_revoked')
                        <div class="note-box">
                            <strong>SE telah dicabut (Revoke).</strong>
                            <p>Arahkan <strong>Penerbitan Ulang Sertifikat Elektronik</strong> sesuai prosedur BSrE. Gunakan tiket ini selama proses dapat dilanjutkan. Setelah terbit ulang, periksa kembali AMS dan pastikan status SE aktif serta WA terverifikasi. <strong>Tandai Selesai</strong> tidak tersedia selama SE masih Revoke.</p>
                        </div>
                    @elseif ($ticket->certificate_validity_status === 'expired')
                        <div class="note-box">
                            <strong>Sertifikat kedaluwarsa.</strong>
                            <p>Jalankan proses Pembaruan, lalu periksa ulang AMS sampai status menjadi aktif. <strong>Tandai Selesai</strong> tidak tersedia selama sertifikat kedaluwarsa.</p>
                        </div>
                    @elseif ($serviceEvent === 'certificate_not_issued')
                        <div class="note-box">
                            <strong>Menunggu set passphrase.</strong>
                            <p>
                                Pemohon melanjutkan set passphrase melalui petunjuk email BSrE.
                                Gunakan tiket ini;
                                <strong>tidak perlu mengisi permohonan baru</strong>. Setelah
                                selesai, periksa ulang AMS. Tiket belum dapat diselesaikan sebelum
                                SE terbit dan WA terverifikasi.
                                @if ($ticket->wa_verification_status === 'unverified')
                                    Nomor WA juga belum terverifikasi dan harus ditindaklanjuti.
                                @endif
                            </p>
                        </div>
                    @endif
                    <div class="message-box">
                        <div class="message-toolbar">
                            <strong>Pratinjau Pesan</strong>
                            <button
                                type="button"
                                class="btn btn-copy btn-small"
                                data-copy-target="service-wa-message">
                                Salin Pesan
                            </button>
                        </div>
                        <div id="service-wa-message" class="message-preview">
                            {{ $serviceMessage }}
                        </div>
                    </div>
                    @unless ($ticket->source === 'simulated')
                        <form
                            target="tte_whatsapp_web"
                            method="post"
                            data-wa-open="1"
                            data-wa-state="wa-state-service-{{ $serviceEvent }}"
                            action="{{ route('tickets.whatsapp.service', $ticket) }}">
                            @csrf
                            <button class="btn btn-wa full" type="submit">Buka WhatsApp ↗</button>
                        </form>
                        <div id="wa-state-service-{{ $serviceEvent }}" aria-live="polite">
                            @if ($serviceNotification?->opened_at)
                                <p class="small muted mt">Tautan dibuka {{
                                    $serviceNotification->opened_at
                                        ->timezone('Asia/Jakarta')
                                        ->format('d M Y H:i')
                                }}</p>
                                @if ($serviceConfirmed)
                                    <div class="alert success mt">
                                        Pengiriman notifikasi telah dikonfirmasi oleh petugas.
                                    </div>
                                @else
                                    <form
                                        method="post"
                                        action="{{ route('tickets.whatsapp.service.confirm', $ticket) }}"
                                        class="mt">
                                        @csrf
                                        <button class="btn btn-outline full" type="submit">
                                            {{
                                                $serviceEvent === 'registration' &&
                                                $ticket->layanan === 'Pendaftaran'
                                                    ? 'Konfirmasi WA & Selesaikan Administrasi'
                                                    : 'Saya Sudah Mengirim Pesan'
                                            }}
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    @endunless
                    <p class="small muted mt">Membuka WhatsApp bukan bukti pesan terkirim atau diterima. Konfirmasi dilakukan manual.</p>
                </section>
            @endif

            @if ($ticket->status === 'Done' && $waMessage)
                @php ($notification = $ticket->whatsappNotifications->firstWhere( 'event_type', 'completed' ))
                <section class="card whatsapp-card" id="tte-final-notification">
                    <div class="section-heading">
                        <div>
                            <div class="eyebrow">LANGKAH 3 / NOTIFIKASI AKHIR</div>
                            <h2>Notifikasi Selesai + Survei</h2>
                        </div>
                        <span class="wa-mark">WA</span>
                    </div>
                    <p class="small muted">Hasil aktual: <strong>{{ $ticket->service_outcome_label }}</strong>. Notifikasi akhir menggunakan hasil tersebut, bukan otomatis layanan yang dipilih pada formulir.</p>
                    <div class="message-box">
                        <div class="message-toolbar">
                            <strong>Pratinjau Pesan</strong>
                            <button
                                type="button"
                                class="btn btn-copy btn-small"
                                data-copy-target="completed-wa-message">
                                Salin Pesan
                            </button>
                        </div>
                        <div id="completed-wa-message" class="message-preview">
                            {{ $waMessage }}
                        </div>
                    </div>
                    @unless ($ticket->source === 'simulated')
                        <form
                            target="tte_whatsapp_web"
                            method="post"
                            data-wa-open="1"
                            data-wa-state="wa-state-final"
                            action="{{ route('tickets.whatsapp', $ticket) }}">
                            @csrf
                            <button class="btn btn-wa full" type="submit">
                                Buka WhatsApp Selesai ↗
                            </button>
                        </form>
                        <div id="wa-state-final" aria-live="polite">
                            @if ($notification?->opened_at)
                                <p class="small muted mt">Tautan dibuka {{
                                    $notification->opened_at
                                        ->timezone('Asia/Jakarta')
                                        ->format('d M Y H:i')
                                }}</p>
                                @if ($notification->confirmed_at)
                                    <div class="alert success mt">
                                        Notifikasi selesai dan tautan survei telah dikonfirmasi oleh
                                        petugas.
                                    </div>
                                @else
                                    <form
                                        method="post"
                                        action="{{ route('tickets.whatsapp.confirm', $ticket) }}"
                                        class="mt">
                                        @csrf
                                        <button type="submit" class="btn btn-outline full">
                                            Saya Sudah Mengirim Pesan
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    @endunless
                </section>
            @endif

            <section class="card" id="tte-ticket-workflow">
                <div class="section-heading">
                    <div>
                        <div class="eyebrow">WORKFLOW</div>
                        <h2>Penanganan Tiket</h2>
                    </div>
                    <span class="stat-icon stat-indigo">▤</span>
                </div>
                @if ($ticket->status === 'Open')
                    <p class="muted">Ambil tiket terlebih dahulu untuk melakukan pemeriksaan AMS BSrE.</p>
                    <form method="post" action="{{ route('tickets.claim', $ticket) }}">
                        @csrf
                        <button class="btn btn-primary full" type="submit">
                            Ambil Tiket Ini →
                        </button>
                    </form>
                @elseif ($ticket->status === 'In Progress')
                    <div class="note-box">
                        <strong>Ditangani oleh {{ $ticket->claimant?->name }}</strong>
                        <p class="small muted">Catatan hanya diperlukan jika ada informasi tambahan. Tidak wajib diisi.</p>
                    </div>
                    <form
                        method="post"
                        action="{{ route('tickets.note', $ticket) }}"
                        class="stack mt">
                        @csrf
                        <label>Catatan tindak lanjut (opsional)
                            <textarea
                                name="note"
                                rows="3"
                                maxlength="3000"
                                placeholder="Catatan pemeriksaan atau tindak lanjut (jika ada)"></textarea>
                        </label>
                        <button class="btn btn-outline full" type="submit">Simpan Catatan</button>
                    </form>
                    <hr />
                    @if (!$serviceEvent)
                        <p class="small muted">Lengkapi pemeriksaan AMS sebelum menyelesaikan tiket.</p>
                    @elseif ($serviceEvent === 'ready_for_completion')
                        <p class="small muted">SE telah aktif setelah tindak lanjut. Konfirmasi hasil aktual sebelum menandai selesai.</p>
                    @elseif (in_array( $serviceEvent, \App\Models\WaMessageTemplate::CORRECTION_EVENTS, true ))
                        @if ($ticket->source === 'simulated')
                            <p class="small muted">Pengembalian melalui WhatsApp tidak tersedia untuk tiket simulasi.</p>
                        @elseif (!$serviceConfirmed)
                            <p class="small muted">Kirim notifikasi Perbaikan Jenis Permohonan, kemudian klik <strong>Saya Sudah Mengirim Pesan</strong> untuk mengaktifkan tombol pengembalian.</p>
                        @else
                            <p class="small muted">Pesan koreksi sudah dikonfirmasi. Klik <strong>Kembalikan Permohonan</strong> untuk penutupan administratif. Tiket tidak menjadi Selesai dan tidak mendapatkan survei.</p>
                        @endif
                    @elseif ($serviceEvent === 'registration' && $ticket->layanan === 'Pendaftaran')
                        <p class="small muted">Kirim <strong>Notifikasi 4 — Pendaftaran Diproses</strong>, kemudian klik <strong>Konfirmasi WA &amp; Selesaikan Administrasi</strong>. Tiket otomatis Selesai dan Notifikasi Selesai + Survei akan tersedia.</p>
                    @elseif (!$completionReady)
                        <p class="small muted">Sertifikat Elektronik harus berstatus <strong>aktif</strong> di AMS dan nomor WA terverifikasi sebelum tiket dapat diselesaikan. Pemeriksaan ulang diperlukan.</p>
                    @elseif (!in_array($serviceEvent, ['reset', 'renewal', 'registration'], true))
                        <p class="small muted">Tiket belum dapat diselesaikan. Tindak lanjuti AMS atau rekomendasi layanan.</p>
                    @elseif (!$serviceConfirmed && $ticket->source !== 'simulated')
                        <p class="small muted">Pastikan tindakan layanan benar-benar dilakukan dan kirim notifikasi WhatsApp sebelum menutup tiket. Jika pengiriman belum dikonfirmasi, hal tersebut dicatat pada aktivitas.</p>
                    @else
                        <p class="small muted">Penanganan sudah dicatat. Anda dapat menandai selesai tanpa catatan wajib.</p>
                    @endif
                    @if (in_array( $serviceEvent, \App\Models\WaMessageTemplate::CORRECTION_EVENTS, true ))
                        <form
                            method="post"
                            action="{{ route('tickets.return-for-correction', $ticket) }}">
                            @csrf
                            <button
                                type="submit"
                                class="btn btn-primary full"
                                @disabled (!$serviceConfirmed || $ticket->source === 'simulated')>
                                Kembalikan Permohonan
                            </button>
                        </form>
                    @elseif (!( $serviceEvent === 'registration' && $ticket->layanan === 'Pendaftaran' ))
                        <form method="post" action="{{ route('tickets.complete', $ticket) }}">
                            @csrf
                            <button
                                type="submit"
                                class="btn btn-primary full"
                                @disabled (!$completionReady)>
                                Tandai Selesai ✓
                            </button>
                        </form>
                    @endif
                    @php ($canRelease = !$ticket->activities ->whereNotIn('action', ['claimed', 'released']) ->count())
                    <form method="post" action="{{ route('tickets.release', $ticket) }}" class="mt">
                        @csrf
                        <button type="submit" class="btn btn-ghost full" @disabled (!$canRelease)>
                            Lepas Tiket (belum dikerjakan)
                        </button>
                    </form>
                    @unless ($canRelease)
                        <p class="small muted mt">Tiket yang sudah diperiksa atau ditindaklanjuti tidak dapat dilepas.</p>
                    @endunless
                @elseif ($ticket->status === 'Returned')
                    <div class="note-box">
                        <strong>Perlu Pengajuan Ulang</strong>
                        <p class="small muted">Notifikasi koreksi sudah dikonfirmasi, permohonan dikembalikan oleh verifikator. Tiket lama dipertahankan dan tidak dihitung sebagai layanan berhasil maupun dikirim survei.</p>
                        <p class="small muted">Layanan diajukan: {{ $ticket->service_label }}. Rekomendasi: {{
                            $ticket->recommended_service === 'Pendaftaran'
                                ? 'Penerbitan Sertifikat Elektronik'
                                : $ticket->recommended_service
                        }}.</p>
                        <a
                            href="{{ route('public.form') }}"
                            class="btn btn-outline"
                            target="_blank"
                            rel="noopener"
                            >Lihat Formulir Pengajuan Baru</a>
                    </div>
                @else
                    <div class="alert success">
                        Tiket selesai pada {{
                            $ticket->completed_at
                                ?->timezone('Asia/Jakarta')
                                ->format('d M Y H:i') ?? '-'
                        }}. Hasil aktual: {{ $ticket->service_outcome_label }}.
                    </div>
                @endif
            </section>
            @if (in_array($ticket->status, ['In Progress', 'Done'], true) &&
                $ticket->claimed_by === auth()->id() &&
                $ticket->source !== 'simulated')
                <section class="card" id="tte-ams-links">
                    <div class="section-heading">
                        <div>
                            <h2>Kirim Ulang Tautan AMS BSrE</h2>
                        </div>
                    </div>
                    <p class="small muted">Gunakan bagian ini jika pemohon belum menerima tautan, masa berlaku tautan habis, atau tautan tidak dapat dibuka.</p>
                    @if ($ticket->status === 'In Progress')
                        <p class="small muted">Untuk pengiriman pertama, ikuti <a href="#tte-ams-check">Pemeriksaan AMS</a> dan konfirmasikan pesan pada langkah Notifikasi Sesuai AMS.</p>
                    @endif
                    <form
                        method="post"
                        action="{{ route('tickets.ams-links.store', $ticket) }}"
                        class="stack mt">
                        @csrf
                        <fieldset class="ams-field">
                            <legend>Jenis tautan yang dikirim ulang</legend>
                            <div class="ams-choices">
                                @foreach (\App\Models\TicketResetResend::LINK_TYPES as $type => $label)
                                    <label class="ams-choice">
                                        <input
                                            type="radio"
                                            name="link_type"
                                            value="{{ $type }}"
                                            @checked (old('link_type') === $type)
                                            required />
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <fieldset class="ams-field">
                            <legend>Alasan kirim ulang</legend>
                            <div class="ams-choices">
                                @foreach (\App\Models\TicketResetResend::REASONS as $code => $description)
                                    <label class="ams-choice">
                                        <input
                                            type="radio"
                                            name="reason"
                                            value="{{ $code }}"
                                            @checked (old('reason') === $code)
                                            required />
                                        <span>{{ $description }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <p class="small muted">BSrE mengirim tautan verifikasi nomor langsung melalui chat WhatsApp ke <strong>{{ $ticket->no_wa ?: '(nomor belum tersedia)' }}</strong>. Tautan aktivasi, pembaruan, dan reset passphrase dikirim ke email dinas <strong>{{ $ticket->email ?: '(email belum tersedia)' }}</strong>.</p>
                        <div class="ams-choices">
                            <label class="ams-choice">
                                <input type="checkbox" name="ams_confirmed" value="1" required />
                                <span>Saya sudah mengirim ulang tautan ini melalui AMS BSrE.</span>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary full">
                            Catat Kirim Ulang &amp; Siapkan Notifikasi WA
                        </button>
                    </form>
                    <p class="small muted mt">Tunggu minimal 2 menit sebelum mencatat kirim ulang berikutnya. Maksimal 5 pengiriman ulang per tiket dalam 24 jam untuk seluruh jenis tautan.</p>
                    @if ($ticket->resetResends->isNotEmpty())
                        <hr />
                        <h3>Riwayat Pengiriman Tautan</h3>
                        @foreach ($ticket->resetResends->sortByDesc('id')->take(10) as $attempt)
                            <details class="note-box mt" {{ $loop->first ? 'open' : '' }}>
                                <summary>
                                    {{
                                        \App\Models\TicketResetResend::LINK_TYPES[
                                            $attempt->link_type
                                        ]
                                    }} · #{{ $attempt->id }} · {{
                                        $attempt->created_at
                                            ->timezone('Asia/Jakarta')
                                            ->format('d/m/Y H:i')
                                    }} · {{
                                        $attempt->wa_confirmed_at
                                            ? 'WA dikonfirmasi'
                                            : 'WA belum dikonfirmasi'
                                    }}
                                </summary>
                                <p class="small muted">{{
                                    \App\Models\TicketResetResend::REASONS[$attempt->reason] ??
                                        ($attempt->reason === 'initial'
                                            ? 'Pengiriman pertama (arsip)'
                                            : $attempt->reason)
                                }}</p>
                                <div class="message-preview">{{ $attempt->message }}</div>
                                @if (!isset(\App\Models\TicketResetResend::REASONS[$attempt->reason]) && !$attempt->wa_confirmed_at)
                                    <p class="small muted mt">Catatan arsip. Konfirmasi WhatsApp belum tercatat.</p>
                                @elseif (!$attempt->wa_confirmed_at && $attempt->verifier_id === auth()->id())
                                    <form
                                        method="post"
                                        target="tte_whatsapp_web"
                                        data-wa-open="1"
                                        data-wa-state="wa-state-resend-{{ $attempt->id }}"
                                        action="{{ route('tickets.ams-links.whatsapp', [$ticket, $attempt]) }}"
                                        class="mt">
                                        @csrf
                                        <button type="submit" class="btn btn-wa full">
                                            Buka WhatsApp ↗
                                        </button>
                                    </form>
                                    <div id="wa-state-resend-{{ $attempt->id }}" aria-live="polite">
                                        @if ($attempt->wa_opened_at)
                                            <form
                                                method="post"
                                                action="{{ route('tickets.ams-links.confirm', [$ticket, $attempt]) }}"
                                                class="mt">
                                                @csrf
                                                <button type="submit" class="btn btn-outline full">
                                                    Saya Sudah Mengirim Pesan
                                                </button>
                                            </form>
                                        @endif
                                    </div>

                                @elseif (!$attempt->wa_confirmed_at)
                                    <p class="small muted mt">Permintaan ini dicatat petugas sebelumnya. Jika perlu, lakukan pengiriman ulang baru melalui AMS.</p>
                                @else
                                    <p class="small muted mt">Pemberitahuan WhatsApp dikonfirmasi {{
                                        $attempt->wa_confirmed_at
                                            ->timezone('Asia/Jakarta')
                                            ->format('d/m/Y H:i')
                                    }} oleh petugas.</p>
                                @endif
                            </details>
                        @endforeach
                    @endif
                </section>
            @endif
        </div>
    </div>
    <link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/tte-ticket.css') }}" />
    <script src="{{ \App\Support\VersionedAsset::url('js/tte-ticket.js') }}" defer></script>
@endsection
