/* TTE v6.13: clipboard helper for the public success page only. */
(() => {
    'use strict';
    const button = document.querySelector('[data-tte-ticket-copy]');
    const number = document.getElementById('tte-ux-ticket-number');
    const feedback = document.getElementById('tte-ux-copy-feedback');
    if (!button || !number || !feedback) return;

    button.addEventListener('click', async () => {
        const value = number.textContent?.trim() || '';
        if (!value) return;
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(value);
            feedback.textContent = 'Nomor tiket berhasil disalin.';
        } catch (_) {
            const selection = window.getSelection();
            if (selection) {
                const range = document.createRange();
                range.selectNodeContents(number);
                selection.removeAllRanges();
                selection.addRange(range);
            }
            feedback.textContent = 'Pilih nomor tiket di atas, lalu salin secara manual.';
        }
    });
})();
