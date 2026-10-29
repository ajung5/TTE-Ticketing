/* Public request: NIK -> masked directory email; pointer-based signature pad. */
(() => {
  'use strict';
  const form = document.getElementById('tte-public-form');
  if (!form) return;
  const nik = document.getElementById('nik');
  const email = document.getElementById('email_display');
  const nikStatus = document.getElementById('nik-status');
  const submit = document.getElementById('public-submit');
  const modal = document.getElementById('email-help-modal');
  const modalClose = document.getElementById('email-modal-close');
  const canvas = document.getElementById('signature-pad');
  const signatureInput = document.getElementById('signature_data');
  const signatureStatus = document.getElementById('signature-status');
  const ctx = canvas.getContext('2d');
  const consent = form.querySelector('[name="persetujuan"]');
  let matched = false, signed = false, drawing = false, debounce, controller, sequence = 0;
  ctx.strokeStyle = '#173759';
  ctx.lineWidth = 3;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';

  function refreshSubmit() {
    submit.disabled = !matched || !signed || !consent.checked;
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
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) closeModal(); });
  consent.addEventListener('change', refreshSubmit);

  nik.addEventListener('input', () => {
    nik.value = nik.value.replace(/\D/g, '').slice(0, 16);
    matched = false;
    email.value = '';
    clearTimeout(debounce);
    controller?.abort();
    sequence++;
    nikStatus.textContent = nik.value.length === 16 ? 'Sedang memeriksa direktori email...' : 'Masukkan 16 digit NIK.';
    refreshSubmit();
    if (nik.value.length !== 16) return;
    const currentSequence = sequence;
    debounce = setTimeout(async () => {
      controller = new AbortController();
      try {
        const response = await fetch(form.dataset.lookupUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
          body: JSON.stringify({ nik: nik.value }),
          signal: controller.signal,
          credentials: 'same-origin',
        });
        const result = await response.json();
        if (currentSequence !== sequence) return;
        matched = response.ok && result.status === 'found';
        email.value = matched ? result.email_masked : '';
        if (matched) nikStatus.textContent = 'Email dinas cocok dengan direktori.';
        else if (result.status === 'not_found') {
          nikStatus.textContent = 'NIK atau username email belum tersedia.';
          openModal();
        } else {
          nikStatus.textContent = result.message || 'Pemeriksaan belum berhasil; coba kembali nanti.';
        }
      } catch (e) {
        if (e.name !== 'AbortError' && currentSequence === sequence) nikStatus.textContent = 'Tidak dapat menghubungi layanan direktori. Coba lagi nanti.';
      } finally {
        refreshSubmit();
      }
    }, 450);
  });

  const point = e => {
    const rect = canvas.getBoundingClientRect();
    return { x: (e.clientX - rect.left) * canvas.width / rect.width, y: (e.clientY - rect.top) * canvas.height / rect.height };
  };
  canvas.addEventListener('pointerdown', e => {
    if (!e.isPrimary) return;
    drawing = true;
    canvas.setPointerCapture(e.pointerId);
    const p = point(e);
    ctx.beginPath(); ctx.moveTo(p.x, p.y);
  });
  canvas.addEventListener('pointermove', e => {
    if (!drawing || !e.isPrimary) return;
    const p = point(e);
    ctx.lineTo(p.x, p.y); ctx.stroke();
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
    if (!matched || !signed || !consent.checked) {
      e.preventDefault();
      return;
    }
    signatureInput.value = canvas.toDataURL('image/png');
    submit.disabled = true;
    submit.textContent = 'Mengirim permohonan...';
  });
  refreshSubmit();
})();
