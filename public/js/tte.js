/* UI helper tanpa framework atau dependensi pihak ketiga. */
(() => {
  'use strict';
  let hideToast;
  const toast = (message, isError = false) => {
    const status = document.getElementById('clipboard-status');
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('is-error', isError);
    status.classList.add('visible');
    clearTimeout(hideToast);
    hideToast = setTimeout(() => status.classList.remove('visible'), 2800);
  };

  async function writeClipboard(value) {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(value);
      return;
    }
    // Fallback untuk browser lama / koneksi intranet non-HTTPS.
    const input = document.createElement('textarea');
    input.value = value;
    input.setAttribute('readonly', '');
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.select();
    const copied = document.execCommand('copy');
    input.remove();
    if (!copied) throw new Error('Clipboard unavailable');
  }

  let focusedPresetEditor = null;
  document.addEventListener('focusin', (event) => {
    if (event.target.matches('.template-textarea')) focusedPresetEditor = event.target;
  });
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-insert-token]');
    if (!button) return;
    const token = button.getAttribute('data-insert-token');
    const textarea = focusedPresetEditor || document.querySelector('.template-textarea');
    if (!textarea || !token) return;
    const start = textarea.selectionStart, end = textarea.selectionEnd;
    textarea.setRangeText(token, start, end, 'end');
    textarea.focus();
    toast('Variabel ' + token + ' dimasukkan ke editor.');
  });

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-target]');
    if (!button) return;
    const target = document.getElementById(button.dataset.copyTarget);
    if (!target) return;
    const original = button.textContent;
    button.disabled = true;
    try {
      await writeClipboard(target.textContent.trim());
      button.textContent = '✓ Tersalin';
      toast('Pesan WhatsApp disalin ke clipboard.');
    } catch (_) {
      toast('Browser memblokir clipboard. Salin pesan secara manual.', true);
    } finally {
      setTimeout(() => { button.textContent = original; button.disabled = false; }, 1800);
    }
  });
})();
