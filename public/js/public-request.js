/* v5 public form: NIK directory autofill, editable profile, immutable full email, touch signature. */
(() => {
  'use strict';
  const form = document.getElementById('tte-public-form');
  if (!form) return;

  const nik = document.getElementById('nik');
  const layanan = document.getElementById('layanan');
  const email = document.getElementById('email_display');
  const nikStatus = document.getElementById('nik-status');
  const details = document.getElementById('request-details');
  const pending = document.getElementById('request-details-pending');
  const submit = document.getElementById('public-submit');
  const modal = document.getElementById('email-help-modal');
  const modalClose = document.getElementById('email-modal-close');
  const canvas = document.getElementById('signature-pad');
  const signatureInput = document.getElementById('signature_data');
  const signatureStatus = document.getElementById('signature-status');
  const consent = form.querySelector('[name="persetujuan"]');
  const profileKeys = ['nama_user', 'nip', 'jabatan', 'opd'];
  const profileInputs = Object.fromEntries(profileKeys.map(key => [key, document.getElementById(key)]));
  const ctx = canvas.getContext('2d');
  let matched = false;
  let signed = false;
  let drawing = false;
  let debounce;
  let controller;
  let sequence = 0;
  let wasRestored = nik.value.length === 16 && profileKeys.some(key => profileInputs[key].value.trim());

  ctx.strokeStyle = '#173759';
  ctx.lineWidth = 3;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';

  function refreshSubmit() {
    submit.disabled = !matched || !signed || !consent.checked || !layanan.value;
  }
  function setMatch(value) {
    matched = value;
    details.hidden = !value;
    pending.hidden = value;
    refreshSubmit();
  }
  function openModal() {
    modal.hidden = false;
    modal.querySelector('.directory-modal-body').focus();
  }
  function closeModal() {
    modal.hidden = true;
    nik.focus();
  }
  modalClose.addEventListener('click', closeModal);
  modal.querySelector('[data-close-directory-modal]').addEventListener('click', closeModal);
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !modal.hidden) closeModal();
  });
  consent.addEventListener('change', refreshSubmit);
  layanan.addEventListener('change', refreshSubmit);

  function setProfile(person, preserveExisting) {
    for (const key of profileKeys) {
      // Old input has priority only after a server-side validation error.
      if (preserveExisting && profileInputs[key].value.trim()) continue;
      profileInputs[key].value = typeof person[key] === 'string' ? person[key] : '';
    }
    // No editable or named email field is submitted. The backend resolves it from the NIK again.
    email.value = person.email || '';
  }

  function lookupNik(preserveExisting = false) {
    clearTimeout(debounce);
    controller?.abort();
    const currentSequence = ++sequence;
    const currentNik = nik.value;
    setMatch(false);
    email.value = '';
    nikStatus.textContent = currentNik.length === 16
      ? 'Memeriksa data pada direktori email...'
      : 'Masukkan 16 digit NIK untuk mencari data.';
    if (currentNik.length !== 16) return;

    debounce = setTimeout(async () => {
      controller = new AbortController();
      try {
        const response = await fetch(form.dataset.lookupUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
          },
          body: JSON.stringify({ nik: currentNik }),
          signal: controller.signal,
          credentials: 'same-origin',
          cache: 'no-store',
        });
        const result = await response.json();
        if (currentSequence !== sequence || currentNik !== nik.value) return;

        if (response.ok && result.status === 'found' && result.email) {
          setProfile(result, preserveExisting);
          nikStatus.textContent = 'Data ditemukan. Periksa dan koreksi informasi jika diperlukan.';
          setMatch(true);
        } else if (result.status === 'not_found') {
          nikStatus.textContent = 'NIK atau username Email Gov tidak tersedia.';
          openModal();
        } else if (response.status === 429) {
          nikStatus.textContent = 'Terlalu banyak pencarian. Silakan coba beberapa saat lagi.';
        } else {
          nikStatus.textContent = result.message || 'Direktori belum bisa diperiksa. Silakan coba lagi.';
        }
      } catch (e) {
        if (e.name !== 'AbortError' && currentSequence === sequence) {
          nikStatus.textContent = 'Tidak dapat terhubung ke direktori. Silakan coba lagi.';
        }
      } finally {
        if (currentSequence === sequence) refreshSubmit();
      }
    }, preserveExisting ? 100 : 450);
  }

  nik.addEventListener('input', () => {
    nik.value = nik.value.replace(/\D/g, '').slice(0, 16);
    for (const key of profileKeys) profileInputs[key].value = '';
    wasRestored = false;
    if (!modal.hidden) modal.hidden = true;
    lookupNik(false);
  });

  const point = e => {
    const rect = canvas.getBoundingClientRect();
    return {
      x: (e.clientX - rect.left) * canvas.width / rect.width,
      y: (e.clientY - rect.top) * canvas.height / rect.height,
    };
  };
  canvas.addEventListener('pointerdown', e => {
    if (!e.isPrimary) return;
    drawing = true;
    canvas.setPointerCapture(e.pointerId);
    const p = point(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
  });
  canvas.addEventListener('pointermove', e => {
    if (!drawing || !e.isPrimary) return;
    const p = point(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    signed = true;
    signatureStatus.textContent = 'Tanda tangan telah diisi.';
    refreshSubmit();
  });
  const endStroke = () => { drawing = false; };
  canvas.addEventListener('pointerup', endStroke);
  canvas.addEventListener('pointercancel', endStroke);
  document.getElementById('signature-clear').addEventListener('click', () => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    signed = false;
    signatureInput.value = '';
    signatureStatus.textContent = 'Tanda tangan belum diisi.';
    refreshSubmit();
  });
  form.addEventListener('submit', e => {
    if (!matched || !signed || !consent.checked || !layanan.value) {
      e.preventDefault();
      return;
    }
    signatureInput.value = canvas.toDataURL('image/png');
    submit.disabled = true;
    submit.textContent = 'Mengirim permohonan...';
  });

  // A failed POST may redirect back with old input; restore corrections, then recheck the NIK.
  if (nik.value.length === 16) lookupNik(wasRestored);
  refreshSubmit();
})();
