@php
    $groups = [
        'activation_status' => [
            '1. Status aktivasi akun pada AMS BSrE',
            'Aktivasi',
            ['activated' => 'Sudah aktivasi', 'not_activated' => 'Belum aktivasi'],
        ],
        'wa_verification_status' => [
            '2. Status verifikasi nomor WhatsApp',
            'WhatsApp',
            ['verified' => 'Sudah terverifikasi', 'unverified' => 'Belum terverifikasi'],
        ],
        'certificate_issuance_status' => [
            '3. Status penerbitan Sertifikat Elektronik',
            'Penerbitan SE',
            ['issued' => 'Sudah Terbit', 'not_issued' => 'Belum Terbit'],
        ],
        'certificate_validity_status' => [
            '4. Status Sertifikat Elektronik',
            'Status SE',
            [
                'active' => 'Aktif',
                'expired' => 'Kedaluwarsa (Expired)',
                'revoked' => 'Dicabut (Revoke)',
            ],
        ],
        'service_alignment_status' => [
            '5. Kesesuaian pilihan layanan',
            'Kesesuaian',
            [
                'suitable' => $continuationMismatch ? 'Lanjut pada tiket ini' : 'Sesuai',
                'unsuitable' => 'Belum sesuai',
            ],
        ],
        'recommended_service' => [
            'Layanan yang disarankan setelah klarifikasi',
            'Rekomendasi',
            [
                'Reset Passphrase' => 'Reset Passphrase',
                'Pembaruan' => 'Pembaruan Sertifikat Elektronik',
            ],
        ],
    ];
    $retry = session()->hasOldInput('activation_status') || $errors->has('activation_status');
    $answer = fn($name) => $retry ? old($name) : $ticket->{$name};
    $note = $answer('wa_verification_note');
    $savedCorrection =
        in_array($ticket->recommended_service, ['Reset Passphrase', 'Pembaruan'], true) &&
        $ticket->recommended_service !== $ticket->layanan;
@endphp
<p class="small muted">Pilih satu hasil per pertanyaan sesuai pemeriksaan di AMS BSrE.</p>
<noscript><p>Aktifkan JavaScript untuk menampilkan tahapan pemeriksaan AMS.</p></noscript>
@foreach ($groups as $name => [$question, $shortLabel, $options])
    @php
        $first = $loop->first;
        $selected = $answer($name);
        if (
            !$retry &&
            $name === 'service_alignment_status' &&
            $ticket->certificate_validity_status === 'active'
        ) {
            // A prior automatic mismatch can continue on this ticket without a new recommendation.
            if ($savedCorrection) {
                $selected = 'unsuitable';
            } elseif ($continuationMismatch) {
                $selected = 'suitable';
            } elseif ($selected === 'unsuitable') {
                $selected = null;
            }
        }
    @endphp
    <fieldset
        class="ams-conditional ams-field"
        data-ams-field="{{ $name }}"
        data-summary-label="{{ $shortLabel }}"
        @if (!$first) hidden disabled @endif>
        <legend>{{ $question }}</legend>
        <div class="ams-choices">
            @foreach ($options as $value => $label)
                @continue ($name === 'recommended_service' && $value === $ticket->layanan)
                <label class="ams-choice">
                    <input
                        type="radio"
                        name="{{ $name }}"
                        value="{{ $value }}"
                        @checked ($selected === $value)
                        @required ($first) />
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @if ($name === 'service_alignment_status')
            <p class="small muted">Pilih “Belum sesuai” jika pemohon perlu mengajukan layanan lain.</p>
        @endif
    </fieldset>
@endforeach
<label for="ams-note"
    >Catatan pemeriksaan (opsional)
    <textarea
        id="ams-note"
        name="wa_verification_note"
        rows="2"
        maxlength="500"
        placeholder="Catatan hasil pemeriksaan tanpa OTP atau passphrase"
        >{{ is_string($note) ? $note : '' }}</textarea
    >
</label>
<section class="ams-summary" aria-labelledby="ams-summary-heading" hidden>
    <h3 id="ams-summary-heading">Ringkasan &amp; tindak lanjut</h3>
    <p id="ams-decision-preview" role="status" aria-live="polite" aria-atomic="true"></p>
    <dl class="ams-recap"></dl>
</section>
<div class="ams-actions">
    <button type="button" class="btn btn-ghost" data-ams-restart hidden>Mulai dari awal</button>
    <button type="submit" class="btn btn-primary" disabled>Simpan Hasil Pemeriksaan AMS</button>
</div>
