/* AMS v27: aktivasi -> WA -> penerbitan -> masa berlaku, tanpa asumsi otomatis. */
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

  function stage(element, block, show, required = show) {
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
    const active = issued && validity.value === 'active';
    const registrationMismatch = verified && active && requested === 'Pendaftaran' && !hasOnboardingHistory;
    const canRecommend = registrationMismatch || (verified && active && existingService);
    stage(recommended, recommendationBlock, canRecommend, registrationMismatch);
    for (const option of recommended.options) {
      option.disabled = option.value !== '' && option.value === requested;
    }
    if (!canRecommend || recommended.value === requested) recommended.value = '';

    let message = 'Mulai dari pemeriksaan aktivasi akun.';
    if (activation.value === 'not_activated') {
      message = existingService
        ? 'Akun belum aktivasi. Pandu aktivasi lebih dahulu dan evaluasi kebutuhan layanan setelah pemeriksaan ulang.'
        : 'Akun belum aktivasi. Berikan panduan aktivasi akun terlebih dahulu.';
    } else if (activated && !hasWa) {
      message = 'Tahap 2: periksa verifikasi nomor WA di AMS BSrE.';
    } else if (hasWa && !cert.value) {
      message = 'Tahap 3: periksa apakah Sertifikat Elektronik pernah diterbitkan.';
    } else if (issued && !validity.value) {
      message = 'Tahap 4: tentukan status sertifikat, masih aktif atau kedaluwarsa.';
    } else if (hasWa && cert.value === 'not_issued') {
      message = 'SE belum terbit. Lanjutkan set passphrase melalui email BSrE pada tiket yang sama.' +
        (wa.value === 'unverified' ? ' Nomor WA juga belum terverifikasi dan harus ditindaklanjuti sebelum penutupan.' : '') +
        ' Periksa ulang AMS setelah proses.';
    } else if (hasWa && wa.value === 'unverified') {
      message = 'WA belum terverifikasi. Status penerbitan dan masa berlaku SE tetap dicatat; lanjutkan verifikasi WA sebelum layanan utama.';
    } else if (expired && verified) {
      message = requested === 'Pembaruan'
        ? 'Sertifikat kedaluwarsa. Proses pembaruan dan periksa ulang sampai sertifikat aktif sebelum Tandai Selesai.'
        : 'Sertifikat kedaluwarsa: sarankan Pembaruan Sertifikat (bukan Reset atau Pendaftaran).';
    } else if (registrationMismatch) {
      message = 'SE sudah aktif. Pendaftaran tidak sesuai; pilih layanan yang benar setelah klarifikasi.';
    } else if (active && verified && existingService && recommended.value) {
      message = 'Layanan berbeda setelah klarifikasi: ' + recommended.value + '. Kirim pesan koreksi sebelum mengembalikan tiket.';
    } else if (active && verified && noticeConfirmed) {
      message = 'SE kini aktif setelah pemberitahuan sebelumnya. Tiket dapat ditandai selesai.';
    } else if (active && verified) {
      message = 'SE aktif dan WA terverifikasi. Lanjutkan penanganan sesuai layanan yang diajukan.';
    }
    indicator.textContent = message;
  }
  for (const element of [activation, wa, cert, validity, recommended]) {
    element.addEventListener('change', refresh);
  }
  refresh();
})();
