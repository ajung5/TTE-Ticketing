/* AMS v35: aktivasi -> WA -> penerbitan -> Aktif/Expired/Revoke -> kesesuaian layanan. */
(() => {
  'use strict';
  const form = document.getElementById('ams-verification-form');
  if (!form) return;
  const activation = form.querySelector('[name="activation_status"]');
  const wa = form.querySelector('[name="wa_verification_status"]');
  const cert = form.querySelector('[name="certificate_issuance_status"]');
  const validity = form.querySelector('[name="certificate_validity_status"]');
  const recommended = form.querySelector('[name="recommended_service"]');
  const waBlock = document.getElementById('ams-wa-block');
  const certBlock = document.getElementById('ams-certificate-block');
  const validityBlock = document.getElementById('ams-validity-block');
  const recommendationBlock = document.getElementById('ams-recommendation-block');
  const indicator = document.getElementById('ams-decision-preview');
  const requested = form.dataset.requestedService;
  const existingService = ['Reset Passphrase', 'Pembaruan'].includes(requested);
  const hasOnboardingHistory = form.dataset.onboardingHistory === '1';
  const noticeConfirmed = form.dataset.issuanceNoticeConfirmed === '1';

  // Frontend progresif: pilihan Revoke ditambah tanpa mengganti markup verifikator lama.
  if (validity && !Array.from(validity.options).some(o => o.value === 'revoked')) {
    validity.add(new Option('Dicabut / Revoke', 'revoked'));
    const summary = document.querySelector('.verification-status');
    if (summary && summary.textContent.includes('Revoke')) validity.value = 'revoked';
  }
  let alignment = form.querySelector('[name="service_alignment_status"]');
  let alignmentBlock = null;
  if (!alignment) {
    alignmentBlock = document.createElement('div');
    alignmentBlock.id = 'ams-service-alignment-block';
    alignmentBlock.className = 'ams-conditional';
    alignmentBlock.hidden = true;
    const label = document.createElement('label');
    label.setAttribute('for', 'ams-service-alignment');
    label.textContent = '5. Kesesuaian layanan yang diajukan';
    alignment = document.createElement('select');
    alignment.id = 'ams-service-alignment';
    alignment.name = 'service_alignment_status';
    alignment.add(new Option('Tentukan kesesuaian layanan', ''));
    alignment.add(new Option('Sesuai', 'suitable'));
    alignment.add(new Option('Belum sesuai', 'unsuitable'));
    label.appendChild(alignment);
    alignmentBlock.appendChild(label);
    const note = document.createElement('p');
    note.className = 'small muted';
    note.textContent = 'Tahapan sementara (aktivasi, set passphrase, atau penerbitan ulang) boleh tetap memakai tiket yang sama meskipun layanan awal berbeda. Kondisi salah layanan yang tidak dapat dilanjutkan akan memerlukan klarifikasi dan rekomendasi.';
    alignmentBlock.appendChild(note);
    const before = recommendationBlock || indicator;
    before.parentNode.insertBefore(alignmentBlock, before);
  } else {
    alignmentBlock = alignment.closest('.ams-conditional');
  }

  function stage(element, block, show, required = show) {
    if (!element || !block) return;
    block.hidden = !show;
    element.disabled = !show;
    element.required = required;
  }
  function refresh() {
    const activated = activation.value === 'activated';
    stage(wa, waBlock, activated);
    const hasWa = activated && ['verified', 'unverified'].includes(wa.value);
    stage(cert, certBlock, hasWa);
    const issued = hasWa && cert.value === 'issued';
    stage(validity, validityBlock, issued);
    const verified = hasWa && wa.value === 'verified';
    const expired = issued && validity.value === 'expired';
    const revoked = issued && validity.value === 'revoked';
    const active = issued && validity.value === 'active';
    const notIssued = hasWa && cert.value === 'not_issued';
    const registrationMismatch = verified && active && requested === 'Pendaftaran' && !hasOnboardingHistory;
    const canRecommend = registrationMismatch || (verified && active && existingService);
    stage(alignment, alignmentBlock, issued && verified && !revoked, false);
    stage(recommended, recommendationBlock, canRecommend, registrationMismatch || alignment.value === 'unsuitable');
    for (const option of recommended.options) {
      option.disabled = option.value !== '' && option.value === requested;
    }
    if (!canRecommend || recommended.value === requested) recommended.value = '';
    if (!issued || revoked) alignment.value = '';
    if (registrationMismatch || (expired && requested !== 'Pembaruan')) alignment.value = 'unsuitable';

    let message = 'Mulai dari pemeriksaan aktivasi akun.';
    if (activation.value === 'not_activated') {
      message = requested === 'Pendaftaran'
        ? 'Pendaftaran Diproses: kirim Notifikasi 4. Setelah pengiriman dikonfirmasi, tiket otomatis Selesai dan survei tersedia. Aktivasi akun tetap menjadi tindak lanjut pemohon.'
        : 'Akun belum aktivasi. Pandu aktivasi dan periksa ulang menggunakan tiket yang sama.';
    } else if (activated && !hasWa) {
      message = 'Tahap 2: periksa verifikasi nomor WA di AMS BSrE.';
    } else if (hasWa && !cert.value) {
      message = 'Tahap 3: periksa apakah Sertifikat Elektronik sudah pernah diterbitkan.';
    } else if (issued && !validity.value) {
      message = 'Tahap 4: periksa status sertifikat: Aktif, Expired, atau Revoke.';
    } else if (notIssued) {
      message = 'SE belum terbit. Lanjutkan set passphrase melalui email BSrE pada tiket yang sama.' +
        (wa.value === 'unverified' ? ' Nomor WA juga harus diverifikasi sebelum penutupan.' : '') +
        ' Periksa ulang AMS setelah proses.';
    } else if (revoked) {
      message = 'SE berstatus Revoke. Arahkan Penerbitan Ulang melalui mekanisme resmi BSrE. Tetap gunakan nomor tiket ini, lalu periksa ulang sampai SE aktif.' +
        (wa.value === 'unverified' ? ' Verifikasi WA juga wajib diselesaikan.' : '');
    } else if (hasWa && wa.value === 'unverified') {
      message = 'WA belum terverifikasi. Status SE tetap dicatat; verifikasi WA harus selesai sebelum penutupan.';
    } else if (expired && verified) {
      message = requested === 'Pembaruan'
        ? 'SE kedaluwarsa. Proses pembaruan dan periksa ulang sampai sertifikat aktif.'
        : 'SE kedaluwarsa. Rekomendasikan Pembaruan Sertifikat Elektronik.';
    } else if (registrationMismatch) {
      message = 'SE sudah aktif sejak sebelum Pendaftaran. Klarifikasi, lalu tentukan layanan yang benar.';
    } else if (active && verified && existingService && recommended.value) {
      message = 'Layanan berbeda setelah klarifikasi: ' + recommended.value + '. Kirim notifikasi koreksi.';
    } else if (active && verified && noticeConfirmed) {
      message = 'SE kini aktif setelah pemberitahuan sebelumnya. Periksa hasil aktual sebelum Tandai Selesai.';
    } else if (active && verified) {
      message = 'SE aktif dan WA terverifikasi. Konfirmasi penyelesaian proses yang benar-benar dilakukan.';
    }
    indicator.textContent = message;
  }
  for (const element of [activation, wa, cert, validity, recommended, alignment]) {
    element.addEventListener('change', refresh);
  }
  refresh();
})();
