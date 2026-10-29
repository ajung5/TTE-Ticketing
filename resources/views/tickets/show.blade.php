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
                                    'claimed' => 'Tiket diambil', 'released' => 'Tiket dilepas',
                                    'note' => 'Catatan tindak lanjut', 'completed' => 'Tiket diselesaikan',
                                    'ams_checked' => 'Pemeriksaan status sertifikat dan WA pada AMS BSrE',
                                    'wa_service_confirmed' => 'Pengiriman notifikasi layanan dikonfirmasi',
                                    'wa_confirmed' => 'Notifikasi selesai + survei dikonfirmasi',
                                    default => $entry->action,
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
                                {{ $ticket->certificate_status === 'active' ? 'Sertifikat aktif' : ($ticket->certificate_status === 'inactive' ? 'Sertifikat belum aktif' : 'Belum diperiksa') }}
                                · {{ $ticket->wa_verification_label }}
                            </strong>
                            @if ($ticket->wa_checked_at)
                                <div class="small muted">Diperiksa {{ $ticket->wa_checked_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</div>
                            @endif
                        </div>
                        <p class="small muted">Periksa NIK dan status sertifikat pengguna pada AMS BSrE, lalu periksa apakah nomor WA telah terverifikasi. Pilih status berdasarkan hasil pemeriksaan manual, bukan dari validitas format nomor.</p>
                        <form method="post" action="{{ route('tickets.verify-whatsapp', $ticket) }}" class="stack">
                            @csrf
                            <label>Status sertifikat elektronik pada AMS BSrE
                                <select name="certificate_status" required>
                                    <option value="">Pilih status sertifikat</option>
                                    <option value="active" @selected(old('certificate_status', $ticket->certificate_status) === 'active')>Sudah memiliki sertifikat aktif</option>
                                    <option value="inactive" @selected(old('certificate_status', $ticket->certificate_status) === 'inactive')>Belum memiliki sertifikat aktif</option>
                                </select>
                            </label>
                            <label>Status verifikasi nomor WhatsApp pada AMS BSrE
                                <select name="wa_verification_status" required>
                                    <option value="">Pilih status WA</option>
                                    <option value="verified" @selected(old('wa_verification_status', $ticket->wa_verification_status) === 'verified')>Sudah terverifikasi</option>
                                    <option value="unverified" @selected(old('wa_verification_status', $ticket->wa_verification_status) === 'unverified')>Belum terverifikasi</option>
                                </select>
                            </label>
                            <label>Catatan pemeriksaan (opsional)
                                <textarea name="wa_verification_note" rows="2" maxlength="500" placeholder="Catatan temuan pada AMS, tanpa OTP atau password">{{ old('wa_verification_note', $ticket->wa_verification_note) }}</textarea>
                            </label>
                            <button type="submit" class="btn btn-primary full">Simpan Hasil Pemeriksaan AMS</button>
                        </form>
                    </div>
                </section>
            @endif

            <section class="card">
                <div class="section-heading">
                    <div><div class="eyebrow">WORKFLOW</div><h2>Penanganan Tiket</h2></div>
                    <span class="stat-icon stat-indigo">▤</span>
                </div>
                @if ($ticket->status === 'Open')
                    <p class="muted">Ambil tiket terlebih dahulu untuk melakukan pemeriksaan status pengguna pada AMS BSrE.</p>
                    <form method="post" action="{{ route('tickets.claim', $ticket) }}">@csrf
                        <button class="btn btn-primary full" type="submit">Ambil Tiket Ini →</button>
                    </form>
                @elseif ($ticket->status === 'In Progress')
                    <div class="note-box"><strong>Ditangani oleh {{ $ticket->claimant?->name }}</strong>
                        <p class="small muted">Catatan hanya diperlukan jika ada informasi tambahan. Tidak wajib diisi.</p>
                    </div>
                    <form method="post" action="{{ route('tickets.note', $ticket) }}" class="stack mt">@csrf
                        <label>Catatan tindak lanjut (opsional)
                            <textarea name="note" rows="3" maxlength="3000" placeholder="Catatan pemeriksaan atau tindak lanjut (jika ada)"></textarea>
                        </label>
                        <button class="btn btn-outline full" type="submit">Simpan Catatan</button>
                    </form>
                    <hr>
                    @if (!$serviceEvent)
                        <p class="small muted">Lengkapi pemeriksaan AMS di bagian atas sebelum menyelesaikan tiket.</p>
                    @elseif (!$serviceConfirmed && $ticket->source !== 'simulated')
                        <p class="small muted">Kirim dan konfirmasi notifikasi layanan terlebih dahulu sebelum menandai tiket selesai.</p>
                    @else
                        <p class="small muted">Penanganan telah dicatat. Anda dapat menandai tiket selesai tanpa mengisi catatan.</p>
                    @endif
                    <form method="post" action="{{ route('tickets.complete', $ticket) }}">@csrf
                        <button type="submit" class="btn btn-primary full"
                            @disabled(!$serviceEvent || (!$serviceConfirmed && $ticket->source !== 'simulated'))>Tandai Selesai ✓</button>
                    </form>
                    @php($canRelease = !$ticket->activities->whereNotIn('action', ['claimed', 'released'])->count())
                    <form method="post" action="{{ route('tickets.release', $ticket) }}" class="mt">@csrf
                        <button type="submit" class="btn btn-ghost full" @disabled(!$canRelease)>Lepas Tiket (belum dikerjakan)</button>
                    </form>
                    @unless($canRelease)
                        <p class="small muted mt">Tiket yang sudah diperiksa atau ditindaklanjuti tidak dapat dilepas.</p>
                    @endunless
                @else
                    <div class="alert success">Tiket selesai pada {{ $ticket->completed_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-' }}.</div>
                @endif
            </section>

            @if ($serviceMessage && $serviceEvent)
                <section class="card whatsapp-card">
                    <div class="section-heading">
                        <div>
                            <div class="eyebrow">LANGKAH 2 / NOTIFIKASI SESUAI AMS</div>
                            <h2>{{ \App\Models\WaMessageTemplate::EVENTS[$serviceEvent] }}</h2>
                        </div><span class="wa-mark">WA</span>
                    </div>
                    <p class="small muted">Sistem menentukan jenis notifikasi dari status sertifikat, verifikasi WA, dan layanan yang dipilih. Periksa isi pesan sebelum dikirim.</p>
                    <div class="message-box">
                        <div class="message-toolbar">
                            <strong>Pratinjau Pesan</strong>
                            <button type="button" class="btn btn-copy btn-small" data-copy-target="service-wa-message">Salin Pesan</button>
                        </div>
                        <div id="service-wa-message" class="message-preview">{{ $serviceMessage }}</div>
                    </div>
                    @unless($ticket->source === 'simulated')
                        <form target="tte_whatsapp_web" method="post" action="{{ route('tickets.whatsapp.service', $ticket) }}">@csrf
                            <button class="btn btn-wa full" type="submit">Buka WhatsApp Web ↗</button>
                        </form>
                        @if ($serviceNotification?->opened_at)
                            <p class="small muted mt">Tautan dibuka {{ $serviceNotification->opened_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</p>
                            @if ($serviceConfirmed)
                                <div class="alert success mt">Pengiriman notifikasi telah dikonfirmasi oleh petugas.</div>
                            @else
                                <form method="post" action="{{ route('tickets.whatsapp.service.confirm', $ticket) }}" class="mt">@csrf
                                    <button class="btn btn-outline full" type="submit">Saya Sudah Mengirim Pesan</button>
                                </form>
                            @endif
                        @endif
                    @endunless
                    <p class="small muted mt">Membuka WhatsApp Web bukan bukti pesan terkirim atau diterima. Konfirmasi dilakukan manual.</p>
                </section>
            @endif

            @if ($ticket->status === 'Done' && $waMessage)
                @php($notification = $ticket->whatsappNotifications->firstWhere('event_type', 'completed'))
                <section class="card whatsapp-card">
                    <div class="section-heading">
                        <div><div class="eyebrow">LANGKAH 3 / NOTIFIKASI AKHIR</div><h2>Notifikasi Selesai + Survei</h2></div>
                        <span class="wa-mark">WA</span>
                    </div>
                    <p class="small muted">Kirim notifikasi penutupan tiket yang menyertakan tautan survei kepuasan internal. Tidak ada pengingat inbox tambahan yang wajib.</p>
                    <div class="message-box">
                        <div class="message-toolbar"><strong>Pratinjau Pesan</strong>
                            <button type="button" class="btn btn-copy btn-small" data-copy-target="completed-wa-message">Salin Pesan</button>
                        </div>
                        <div id="completed-wa-message" class="message-preview">{{ $waMessage }}</div>
                    </div>
                    @unless ($ticket->source === 'simulated')
                        <form target="tte_whatsapp_web" method="post" action="{{ route('tickets.whatsapp', $ticket) }}">@csrf
                            <button class="btn btn-wa full" type="submit">Buka WhatsApp Selesai ↗</button>
                        </form>
                        @if ($notification?->opened_at)
                            <p class="small muted mt">Tautan dibuka {{ $notification->opened_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</p>
                            @if ($notification->confirmed_at)
                                <div class="alert success mt">Notifikasi selesai dan tautan survei telah dikonfirmasi oleh petugas.</div>
                            @else
                                <form method="post" action="{{ route('tickets.whatsapp.confirm', $ticket) }}" class="mt">@csrf
                                    <button type="submit" class="btn btn-outline full">Saya Sudah Mengirim Pesan</button>
                                </form>
                            @endif
                        @endif
                    @endunless
                </section>
            @endif
        </div>
    </div>
@endsection
