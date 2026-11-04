@extends('layouts.app')
@section('title', 'Detail Tiket')
@section('content')
    <div class="page-heading">
        <div><a href="{{ route('tickets.index') }}" class="breadcrumb-link">← Kembali ke antrean</a>
            <div class="eyebrow mt">DETAIL PERMOHONAN</div>
            <h1>{{ $ticket->ticket_number ?? '#' . $ticket->id }}</h1>
            <p class="muted">Layanan diajukan: {{ $ticket->service_label }}</p>
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
                    <div><div class="field-name">Nama Pemohon</div><div class="field-value">{{ $ticket->nama_user }}</div></div>
                    <div><div class="field-name">Nomor WhatsApp</div><div class="field-value">{{ $ticket->no_wa }}</div></div>
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
                    <div><div class="field-name">NIP</div><div class="field-value">{{ $ticket->nip ?: '-' }}</div></div>
                    <div><div class="field-name">Unit Kerja / OPD</div><div class="field-value">{{ $ticket->opd ?: '-' }}</div></div>
                    @if ($applicantUptd !== '')
                        <div><div class="field-name">UPTD</div><div class="field-value">{{ $applicantUptd }}</div></div>
                    @endif
                    <div><div class="field-name">Jabatan</div><div class="field-value">{{ $ticket->jabatan ?: '-' }}</div></div>
                    <div><div class="field-name">Email Dinas</div><div class="field-value">{{ $ticket->email ?: '-' }}</div></div>
                    <div>
                        <div class="field-name">Tanggal Masuk</div>
                        <div class="field-value">{{ $ticket->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</div>
                    </div>
                    <div><div class="field-name">Penanggung Jawab</div><div class="field-value">{{ $ticket->claimant?->name ?? 'Belum diambil' }}</div></div>
                </div>
                <div class="note-box mt">
                    <strong>Pemeriksaan dan Hasil Pelayanan</strong>
                    <div class="detail-grid mt">
                        <div><div class="field-name">Layanan Diajukan</div><div class="field-value">{{ $ticket->service_label }}</div></div>
                        <div><div class="field-name">Kesesuaian</div><div class="field-value">{{ $ticket->service_alignment_label }}</div></div>
                        <div><div class="field-name">Proses Aktual</div><div class="field-value">{{ $ticket->actual_process_label }}</div></div>
                        <div><div class="field-name">Hasil Akhir</div><div class="field-value">{{ $ticket->status === 'Done' ? $ticket->service_outcome_label : 'Belum selesai' }}</div></div>
                    </div>
                    <p class="small muted mt">Layanan awal tidak ditimpa. Proses dan hasil sebenarnya dicatat terpisah demi ketepatan notifikasi serta laporan.</p>
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
                    <div class="note-box"><div class="field-name">Keterangan pemohon</div>
                        <div class="pre-wrap">{{ $ticket->keterangan }}</div></div>
                @endif
            </section>
            <section class="card">
                <div class="section-heading">
                    <div><div class="eyebrow">JEJAK AKTIVITAS</div><h2>Riwayat Penanganan</h2></div>
                    <span class="pill pill-neutral">{{ $ticket->activities->count() }} aktivitas</span>
                </div>
                <div class="timeline">
                    @forelse($ticket->activities as $entry)
                        <div class="timeline-item">
                            <div><strong>{{ match ($entry->action) {
                                'claimed' => 'Tiket diambil',
                                'released' => 'Tiket dilepas',
                                'note' => 'Catatan tindak lanjut',
                                'completed' => 'Tiket diselesaikan',
                                'ams_checked' => 'Pemeriksaan akun, WA, penerbitan, dan status SE di AMS',
                                'wa_service_confirmed' => 'Pengiriman notifikasi layanan dikonfirmasi',
                                'wa_confirmed' => 'Notifikasi selesai + survei dikonfirmasi',
                                default => $entry->action
                            } }}</strong>
                                <div class="small muted">{{ $entry->actor?->name ?? 'Sistem' }} ·
                                    {{ $entry->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</div></div>
                            @if ($entry->note)<p class="pre-wrap">{{ $entry->note }}</p>@endif
                        </div>
                    @empty
                        <p class="muted">Belum ada aktivitas.</p>
                    @endforelse
                </div>
            </section>
        </div>
        <div class="stack">
            @if ($ticket->status === 'In Progress')
                @php
                    $issuanceNoticeConfirmed = $ticket->activities
                        ->where('action', 'wa_service_confirmed')
                        ->whereIn('note', [
                            \App\Models\WaMessageTemplate::EVENTS['certificate_not_issued'],
                            \App\Models\WaMessageTemplate::EVENTS['certificate_revoked'],
                            \App\Models\WaMessageTemplate::EVENTS['renewal']
                        ])->isNotEmpty();
                    $onboardingHistory = $ticket->activities
                        ->where('action', 'wa_service_confirmed')
                        ->whereIn('note', [
                            \App\Models\WaMessageTemplate::EVENTS['unregistered'],
                            \App\Models\WaMessageTemplate::EVENTS['activation_required'],
                            \App\Models\WaMessageTemplate::EVENTS['certificate_not_issued'],
                            \App\Models\WaMessageTemplate::EVENTS['certificate_revoked']
                        ])->isNotEmpty();
                @endphp
                <section class="card">
                    <div class="section-heading"><div>
                        <div class="eyebrow">LANGKAH 1 / PEMERIKSAAN AMS</div>
                        <h2>Pemeriksaan Akun, WhatsApp, Sertifikat, dan Layanan</h2>
                    </div></div>
                    <div class="verification-panel">
                        <div class="verification-tag"><strong class="verification-status">
                            Akun: {{ $ticket->activation_label }} ·
                            WA: {{ $ticket->wa_verification_label }} ·
                            {{ $ticket->certificate_status_label }}
                        </strong>
                            @if ($ticket->wa_checked_at)
                                <div class="small muted">Pemeriksaan terakhir:
                                    {{ $ticket->wa_checked_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</div>
                            @endif
                        </div>
                        <p class="small muted">Urutan pemeriksaan pada <strong>AMS BSrE</strong>:
                            <strong>1. Aktivasi akun</strong>, <strong>2. Verifikasi WhatsApp</strong>,
                            <strong>3. Penerbitan Sertifikat Elektronik</strong>,
                            <strong>4. Status SE (Aktif, Expired, Revoke)</strong>, dan
                            <strong>5. Kesesuaian pilihan layanan</strong>.
                            Status sudah terbit tidak otomatis berarti sertifikat aktif.</p>
                        <form method="post" action="{{ route('tickets.verify-whatsapp', $ticket) }}" class="stack"
                            id="ams-verification-form" data-requested-service="{{ $ticket->layanan }}"
                            data-onboarding-history="{{ $onboardingHistory ? '1' : '0' }}"
                            data-issuance-notice-confirmed="{{ $issuanceNoticeConfirmed ? '1' : '0' }}">
                            @csrf
                            <label for="ams-activation-status">1. Status aktivasi akun pada AMS BSrE
                                <select id="ams-activation-status" name="activation_status" required>
                                    <option value="">Pilih status aktivasi akun</option>
                                    <option value="activated" @selected(old('activation_status', $ticket->activation_status) === 'activated')>Sudah aktivasi</option>
                                    <option value="not_activated" @selected(old('activation_status', $ticket->activation_status) === 'not_activated')>Belum aktivasi</option>
                                </select>
                            </label>
                            <div class="ams-conditional" id="ams-wa-block" hidden><label for="ams-wa-status">
                                2. Status verifikasi nomor WhatsApp
                                <select id="ams-wa-status" name="wa_verification_status" disabled>
                                    <option value="">Pilih status verifikasi WA</option>
                                    <option value="verified" @selected(old('wa_verification_status', $ticket->wa_verification_status) === 'verified')>Sudah terverifikasi</option>
                                    <option value="unverified" @selected(old('wa_verification_status', $ticket->wa_verification_status) === 'unverified')>Belum terverifikasi</option>
                                </select>
                            </label></div>
                            <div class="ams-conditional" id="ams-certificate-block" hidden><label for="ams-certificate-status">
                                3. Status penerbitan Sertifikat Elektronik
                                <select id="ams-certificate-status" name="certificate_issuance_status" disabled>
                                    <option value="">Pilih status penerbitan</option>
                                    <option value="issued" @selected(old('certificate_issuance_status', $ticket->certificate_issuance_status) === 'issued')>Sudah/pernah terbit</option>
                                    <option value="not_issued" @selected(old('certificate_issuance_status', $ticket->certificate_issuance_status) === 'not_issued')>Belum pernah terbit</option>
                                </select>
                            </label></div>
                            <div class="ams-conditional" id="ams-validity-block" hidden><label for="ams-validity-status">
                                4. Status Sertifikat Elektronik
                                <select id="ams-validity-status" name="certificate_validity_status" disabled>
                                    <option value="">Pilih status sertifikat</option>
                                    <option value="active" @selected(old('certificate_validity_status', $ticket->certificate_validity_status) === 'active')>Aktif / masih berlaku</option>
                                    <option value="expired" @selected(old('certificate_validity_status', $ticket->certificate_validity_status) === 'expired')>Kedaluwarsa / Expired</option>
                                    <option value="revoked" @selected(old('certificate_validity_status', $ticket->certificate_validity_status) === 'revoked')>Dicabut / Revoke</option>
                                </select>
                            </label></div>
                            <div class="ams-conditional" id="ams-service-alignment-block" hidden>
                                <label for="ams-service-alignment">5. Kesesuaian pilihan layanan
                                    <select id="ams-service-alignment" name="service_alignment_status" disabled>
                                        <option value="">Pilih kesesuaian layanan</option>
                                        <option value="suitable" @selected(old('service_alignment_status', $ticket->service_alignment_status) === 'suitable')>Sesuai</option>
                                        <option value="unsuitable" @selected(old('service_alignment_status', $ticket->service_alignment_status) === 'unsuitable')>Belum sesuai</option>
                                    </select>
                                </label>
                                <p class="small muted">Tahapan sementara dapat dilanjutkan pada tiket yang sama;
                                    layanan tidak sesuai yang tidak dapat dilanjutkan perlu klarifikasi.</p>
                            </div>
                            <div class="ams-conditional" id="ams-recommendation-block" hidden>
                                <label for="ams-recommended-service">Layanan yang disarankan (setelah klarifikasi)
                                    <select id="ams-recommended-service" name="recommended_service" disabled>
                                        <option value="">{{ $ticket->layanan === 'Pendaftaran' ? 'Pilih layanan yang sesuai' : 'Layanan sudah sesuai' }}</option>
                                        <option value="Reset Passphrase" @selected(old('recommended_service', $ticket->recommended_service) === 'Reset Passphrase')>Reset Passphrase</option>
                                        <option value="Pembaruan" @selected(old('recommended_service', $ticket->recommended_service) === 'Pembaruan')>Pembaruan Sertifikat Elektronik</option>
                                    </select>
                                </label>
                                <p class="small muted">SE Expired diarahkan ke Pembaruan; SE Revoke diarahkan ke Penerbitan Ulang.</p>
                            </div>
                            <div id="ams-decision-preview" class="ams-decision-preview small muted" role="status"
                                aria-live="polite">Lengkapi hasil pemeriksaan AMS.</div>
                            <label for="ams-note">Catatan pemeriksaan (opsional)
                                <textarea id="ams-note" name="wa_verification_note" rows="2" maxlength="500"
                                    placeholder="Catatan hasil pemeriksaan; jangan masukkan OTP atau passphrase">{{ old('wa_verification_note', $ticket->wa_verification_note) }}</textarea>
                            </label>
                            <button type="submit" class="btn btn-primary full">Simpan Hasil Pemeriksaan AMS</button>
                        </form>
                    </div>
                </section>
            @endif
            @if ($serviceEvent === 'ready_for_completion')
                <section class="card">
                    <div class="section-heading"><div>
                        <div class="eyebrow">LANGKAH 2 / HASIL PEMERIKSAAN ULANG</div>
                        <h2>SE Sudah Terbit — Siap Ditandai Selesai</h2>
                    </div></div>
                    <div class="note-box">
                        <strong>Sertifikat Elektronik telah dikonfirmasi aktif.</strong>
                        <p class="small muted">Notifikasi tindak lanjut sebelumnya sudah dikonfirmasi.
                            Anda dapat menekan <strong>Tandai Selesai</strong> tanpa mengirim notifikasi antara lagi.
                            Hasil akhir akan mengikuti proses aktual, bukan otomatis nama layanan awal.
                            Setelah selesai, notifikasi <strong>Selesai + Survei</strong> tersedia.</p>
                    </div>
                </section>
            @endif

            @if ($serviceMessage && $serviceEvent)
                <section class="card whatsapp-card">
                    <div class="section-heading"><div>
                        <div class="eyebrow">LANGKAH 2 / NOTIFIKASI SESUAI AMS</div>
                        <h2>{{ \App\Models\WaMessageTemplate::EVENTS[$serviceEvent] }}</h2>
                    </div><span class="wa-mark">WA</span></div>
                    <p class="small muted">Notifikasi mengikuti kondisi aktual AMS dan tahap pelayanan.
                        Periksa isi pesan sebelum dikirim.</p>
                    @if (in_array($serviceEvent, \App\Models\WaMessageTemplate::CORRECTION_EVENTS, true))
                        <div class="note-box" role="alert"><strong>Permohonan perlu diajukan ulang.</strong>
                            <p>Setelah pesan dikirim, klik <strong>Saya Sudah Mengirim Pesan</strong>, lalu
                                <strong>Kembalikan Permohonan</strong>. Tiket lama dipertahankan berstatus
                                <strong>Perlu Pengajuan Ulang</strong>; tidak dihitung selesai.
                                Rekomendasi: {{ $ticket->recommended_service === 'Pendaftaran' ? 'Penerbitan Sertifikat Elektronik' : $ticket->recommended_service }}.
                            </p>
                        </div>
                    @elseif ($serviceEvent === 'certificate_revoked')
                        <div class="note-box"><strong>SE telah dicabut (Revoke).</strong>
                            <p>Arahkan <strong>Penerbitan Ulang Sertifikat Elektronik</strong> sesuai prosedur BSrE.
                                Gunakan tiket ini selama proses dapat dilanjutkan. Setelah terbit ulang, periksa
                                kembali AMS dan pastikan status SE aktif serta WA terverifikasi.
                                <strong>Tandai Selesai</strong> tidak tersedia selama SE masih Revoke.</p>
                        </div>
                    @elseif ($ticket->certificate_validity_status === 'expired')
                        <div class="note-box"><strong>Sertifikat kedaluwarsa.</strong>
                            <p>Jalankan proses Pembaruan, lalu periksa ulang AMS sampai status menjadi aktif.
                                <strong>Tandai Selesai</strong> tidak tersedia selama sertifikat kedaluwarsa.</p>
                        </div>
                    @elseif ($serviceEvent === 'certificate_not_issued')
                        <div class="note-box"><strong>Menunggu set passphrase.</strong>
                            <p>Pemohon melanjutkan set passphrase melalui petunjuk email BSrE.
                                Gunakan tiket ini; <strong>tidak perlu mengisi permohonan baru</strong>.
                                Setelah selesai, periksa ulang AMS. Tiket belum dapat diselesaikan sebelum SE terbit dan WA terverifikasi.
                                @if ($ticket->wa_verification_status === 'unverified')
                                    Nomor WA juga belum terverifikasi dan harus ditindaklanjuti.
                                @endif
                            </p>
                        </div>
                    @endif
                    <div class="message-box"><div class="message-toolbar">
                        <strong>Pratinjau Pesan</strong>
                        <button type="button" class="btn btn-copy btn-small"
                            data-copy-target="service-wa-message">Salin Pesan</button>
                    </div><div id="service-wa-message" class="message-preview">{{ $serviceMessage }}</div></div>
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
                    <p class="small muted mt">Membuka WhatsApp Web bukan bukti pesan terkirim atau diterima.
                        Konfirmasi dilakukan manual.</p>
                </section>
            @endif

            @if ($ticket->status === 'Done' && $waMessage)
                @php($notification = $ticket->whatsappNotifications->firstWhere('event_type', 'completed'))
                <section class="card whatsapp-card">
                    <div class="section-heading"><div>
                        <div class="eyebrow">LANGKAH 3 / NOTIFIKASI AKHIR</div>
                        <h2>Notifikasi Selesai + Survei</h2>
                    </div><span class="wa-mark">WA</span></div>
                    <p class="small muted">Hasil aktual: <strong>{{ $ticket->service_outcome_label }}</strong>.
                        Notifikasi akhir menggunakan hasil tersebut, bukan otomatis layanan yang dipilih pada formulir.</p>
                    <div class="message-box"><div class="message-toolbar">
                        <strong>Pratinjau Pesan</strong>
                        <button type="button" class="btn btn-copy btn-small"
                            data-copy-target="completed-wa-message">Salin Pesan</button>
                    </div><div id="completed-wa-message" class="message-preview">{{ $waMessage }}</div></div>
                    @unless ($ticket->source === 'simulated')
                        <form target="tte_whatsapp_web" method="post" data-wa-open="1"
                            action="{{ route('tickets.whatsapp', $ticket) }}">@csrf
                            <button class="btn btn-wa full" type="submit">Buka WhatsApp Selesai ↗</button>
                        </form>
                        @if ($notification?->opened_at)
                            <p class="small muted mt">Tautan dibuka
                                {{ $notification->opened_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</p>
                            @if ($notification->confirmed_at)
                                <div class="alert success mt">Notifikasi selesai dan tautan survei telah dikonfirmasi oleh petugas.</div>
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
                <div class="section-heading"><div>
                    <div class="eyebrow">WORKFLOW</div><h2>Penanganan Tiket</h2>
                </div><span class="stat-icon stat-indigo">▤</span></div>
                @if ($ticket->status === 'Open')
                    <p class="muted">Ambil tiket terlebih dahulu untuk melakukan pemeriksaan AMS BSrE.</p>
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
                        <p class="small muted">Lengkapi pemeriksaan AMS sebelum menyelesaikan tiket.</p>
                    @elseif ($serviceEvent === 'ready_for_completion')
                        <p class="small muted">SE telah aktif setelah tindak lanjut. Konfirmasi hasil aktual sebelum menandai selesai.</p>
                    @elseif (in_array($serviceEvent, \App\Models\WaMessageTemplate::CORRECTION_EVENTS, true))
                        @if ($ticket->source === 'simulated')
                            <p class="small muted">Pengembalian melalui WhatsApp tidak tersedia untuk tiket simulasi.</p>
                        @elseif (!$serviceConfirmed)
                            <p class="small muted">Kirim notifikasi Perbaikan Jenis Permohonan, kemudian klik
                                <strong>Saya Sudah Mengirim Pesan</strong> untuk mengaktifkan tombol pengembalian.</p>
                        @else
                            <p class="small muted">Pesan koreksi sudah dikonfirmasi. Klik
                                <strong>Kembalikan Permohonan</strong> untuk penutupan administratif.
                                Tiket tidak menjadi Selesai dan tidak mendapatkan survei.</p>
                        @endif
                    @elseif (!$completionReady)
                        <p class="small muted">Sertifikat Elektronik harus berstatus <strong>aktif</strong> di AMS
                            dan nomor WA terverifikasi sebelum tiket dapat diselesaikan. Pemeriksaan ulang diperlukan.</p>
                    @elseif (!in_array($serviceEvent, ['reset', 'renewal', 'registration'], true))
                        <p class="small muted">Tiket belum dapat diselesaikan. Tindak lanjuti AMS atau rekomendasi layanan.</p>
                    @elseif (!$serviceConfirmed && $ticket->source !== 'simulated')
                        <p class="small muted">Pastikan tindakan layanan benar-benar dilakukan dan kirim notifikasi WhatsApp
                            sebelum menutup tiket. Jika pengiriman belum dikonfirmasi, hal tersebut dicatat pada aktivitas.</p>
                    @else
                        <p class="small muted">Penanganan sudah dicatat. Anda dapat menandai selesai tanpa catatan wajib.</p>
                    @endif
                    @if (in_array($serviceEvent, \App\Models\WaMessageTemplate::CORRECTION_EVENTS, true))
                        <form method="post" action="{{ route('tickets.return-for-correction', $ticket) }}">@csrf
                            <button type="submit" class="btn btn-primary full"
                                @disabled(!$serviceConfirmed || $ticket->source === 'simulated')>Kembalikan Permohonan</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('tickets.complete', $ticket) }}">@csrf
                            <button type="submit" class="btn btn-primary full"
                                @disabled(!$completionReady)>Tandai Selesai ✓</button>
                        </form>
                    @endif
                    @php($canRelease = !$ticket->activities->whereNotIn('action', ['claimed', 'released'])->count())
                    <form method="post" action="{{ route('tickets.release', $ticket) }}" class="mt">@csrf
                        <button type="submit" class="btn btn-ghost full" @disabled(!$canRelease)>Lepas Tiket (belum dikerjakan)</button>
                    </form>
                    @unless ($canRelease)
                        <p class="small muted mt">Tiket yang sudah diperiksa atau ditindaklanjuti tidak dapat dilepas.</p>
                    @endunless
                @elseif ($ticket->status === 'Returned')
                    <div class="note-box"><strong>Perlu Pengajuan Ulang</strong>
                        <p class="small muted">Notifikasi koreksi sudah dikonfirmasi, permohonan dikembalikan oleh verifikator.
                            Tiket lama dipertahankan dan tidak dihitung sebagai layanan berhasil maupun dikirim survei.</p>
                        <p class="small muted">Layanan diajukan: {{ $ticket->service_label }}. Rekomendasi:
                            {{ $ticket->recommended_service === 'Pendaftaran' ? 'Penerbitan Sertifikat Elektronik' : $ticket->recommended_service }}.</p>
                        <a href="{{ route('public.form') }}" class="btn btn-outline" target="_blank"
                            rel="noopener">Lihat Formulir Pengajuan Baru</a>
                    </div>
                @else
                    <div class="alert success">Tiket selesai pada
                        {{ $ticket->completed_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }}.
                        Hasil aktual: {{ $ticket->service_outcome_label }}.</div>
                @endif
            </section>
        </div>
    </div>
    <link rel="stylesheet" href="{{ asset('css/ams-workflow.css') }}?v=35">
    <script src="{{ asset('js/ams-workflow.js') }}?v=35" defer></script>
    <script src="{{ asset('js/whatsapp-open-refresh.js') }}?v=1" defer></script>
@endsection
