/* AMS v16: dropdown bertahap, tanpa radio/checkbox; validasi server tetap otoritatif. */
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
  const hasOnboardingHistory = form.dataset.onboardingHistory === '1';
  function refresh() {
    const unverified = wa.value === 'unverified';
    activationBlock.hidden = !unverified;
    activation.disabled = !unverified;
    activation.required = unverified;
    const needsCert = unverified && activation.value === 'activated';
    certBlock.hidden = !needsCert;
    cert.disabled = !needsCert;
    cert.required = needsCert;
    const unregisteredWrongService = unverified && activation.value === 'not_activated' && requested !== 'Pendaftaran';
    const issued = wa.value === 'verified' || (unverified && activation.value === 'activated' && cert.value === 'issued');
    const registrationMismatch = requested === 'Pendaftaran' && issued && !hasOnboardingHistory;
    const mismatch = unregisteredWrongService || registrationMismatch;
    recommendationBlock.hidden = !registrationMismatch;
    recommended.disabled = !registrationMismatch;
    recommended.required = registrationMismatch;
    let message = 'Lengkapi hasil pemeriksaan AMS.';
    if (unregisteredWrongService) message = 'Layanan tidak sesuai: sarankan Penerbitan Sertifikat Elektronik.';
    else if (registrationMismatch) message = 'Penerbitan tidak sesuai karena SE sudah terbit. Tentukan layanan yang tepat setelah klarifikasi.';
    else if (wa.value === 'verified') message = requested === 'Pembaruan' ? 'Notifikasi Pembaruan Sertifikat.' : requested === 'Reset Passphrase' ? 'Notifikasi Reset Passphrase.' : 'Pendaftaran diproses berdasarkan riwayat tiket.';
    else if (unverified && activation.value === 'not_activated') message = 'Notifikasi User Belum Terdaftar.';
    else if (needsCert && cert.value === 'not_issued') message = 'Notifikasi SE Belum Terbit. Tiket tetap dalam proses.';
    else if (needsCert && cert.value === 'issued') message = 'Notifikasi Nomor WA Belum Terverifikasi.';
    indicator.textContent = message;
  }
  for (const element of [wa, activation, cert, recommended]) element.addEventListener('change', refresh);
  refresh();
})();
