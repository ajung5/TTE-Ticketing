/* AMS v24: keputusan penerbitan dan koreksi layanan berdasarkan pemeriksaan manual. */
(() => {
  'use strict';
  const form = document.getElementById('ams-verification-form');
  if (!form) return;
  const wa = form.querySelector('[name="wa_verification_status"]');
  const activation = form.querySelector('[name="activation_status"]');
  const cert = form.querySelector('[name="certificate_issuance_status"]');
  const recommended = form.querySelector('[name="recommended_service"]');
  const activationBlock = document.getElementById('ams-activation-block');
  const certBlock = document.getElementById('ams-certificate-block');
  const recommendationBlock = document.getElementById('ams-recommendation-block');
  const indicator = document.getElementById('ams-decision-preview');
  const requested = form.dataset.requestedService;
  const existingService = ['Reset Passphrase', 'Pembaruan'].includes(requested);
  const hasOnboardingHistory = form.dataset.onboardingHistory === '1';
  const issuanceNoticeConfirmed = form.dataset.issuanceNoticeConfirmed === '1';
  function refresh() {
    const unverified = wa.value === 'unverified';
    activationBlock.hidden = !unverified;
    activation.disabled = !unverified;
    activation.required = unverified;
    const needsCert = wa.value === 'verified' || (unverified && activation.value === 'activated');
    certBlock.hidden = !needsCert;
    cert.disabled = !needsCert;
    cert.required = needsCert;
    const issued = needsCert && cert.value === 'issued';
    const unregisteredWrongService = existingService && unverified && activation.value === 'not_activated';
    const noCertificateMismatch = existingService && needsCert && cert.value === 'not_issued';
    const registrationMismatch = requested === 'Pendaftaran' && issued && !hasOnboardingHistory;
    const canRecommend = registrationMismatch || (existingService && issued);
    recommendationBlock.hidden = !canRecommend;
    recommended.disabled = !canRecommend;
    recommended.required = registrationMismatch;
    for (const option of recommended.options) {
      option.disabled = option.value !== '' && option.value === requested;
    }
    if (!canRecommend || recommended.value === requested) recommended.value = '';
    const clarifiedMismatch = existingService && issued && recommended.value !== '';
    let message = 'Lengkapi hasil pemeriksaan AMS.';
    if (unregisteredWrongService) message = 'Layanan tidak sesuai: sarankan Penerbitan Sertifikat Elektronik.';
    else if (noCertificateMismatch) message = 'SE belum pernah terbit: sarankan Penerbitan Sertifikat Elektronik. Kirim dan konfirmasi pesan penyesuaian, lalu Kembalikan Permohonan.';
    else if (registrationMismatch) message = 'Penerbitan tidak sesuai karena SE sudah/pernah terbit. Tentukan layanan yang tepat setelah klarifikasi.';
    else if (clarifiedMismatch) message = 'Hasil klarifikasi: sarankan ' + recommended.value + '. Kirim dan konfirmasi pesan koreksi, lalu Kembalikan Permohonan.';
    else if (needsCert && cert.value === 'not_issued') message = 'Notifikasi SE Belum Terbit. Pemohon perlu mengikuti petunjuk email BSrE; tiket tetap dalam proses.';
    else if (requested === 'Pendaftaran' && issued && wa.value === 'verified' && issuanceNoticeConfirmed) message = 'SE sudah terbit setelah notifikasi sebelumnya. Tiket siap ditandai selesai.';
    else if (wa.value === 'verified' && issued) message = requested === 'Pembaruan' ? 'Notifikasi Pembaruan Sertifikat.' : requested === 'Reset Passphrase' ? 'Notifikasi Reset Passphrase.' : 'Pendaftaran diproses berdasarkan riwayat tiket.';
    else if (unverified && activation.value === 'not_activated') message = 'Notifikasi User Belum Terdaftar.';
    else if (unverified && issued) message = 'Notifikasi Nomor WA Belum Terverifikasi.';
    else if (wa.value === 'verified' && !cert.value) message = 'Pilih status penerbitan SE. WA terverifikasi tidak menjamin sertifikat sudah terbit.';
    indicator.textContent = message;
  }
  for (const element of [wa, activation, cert, recommended]) element.addEventListener('change', refresh);
  refresh();
})();
