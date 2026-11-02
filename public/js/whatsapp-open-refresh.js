/* TTE-Ticketing: refresh the ticket after WhatsApp-opened_at is persisted. */
(() => {
    'use strict';

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.waOpen !== '1') return;

        event.preventDefault();
        if (form.dataset.submitting === '1') return;

        const targetName = form.getAttribute('target') || '_blank';
        const waTab = window.open('about:blank', targetName);
        if (!waTab) {
            window.alert('Tab WhatsApp diblokir browser. Izinkan pop-up untuk situs ini lalu coba kembali.');
            return;
        }

        form.dataset.submitting = '1';
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) throw new Error(`Permintaan gagal (${response.status}).`);
            const result = await response.json();
            const destination = new URL(result.url);
            if (destination.protocol !== 'https:' || destination.hostname !== 'web.whatsapp.com') {
                throw new Error('Alamat WhatsApp Web dari server tidak valid.');
            }

            waTab.opener = null;
            waTab.location.replace(destination.href);
            // Reload setelah server berhasil mencatat opened_at agar tombol
            // konfirmasi dirender pada halaman tiket di tab asal.
            window.location.reload();
        } catch (error) {
            waTab.close();
            window.alert(error instanceof Error ? error.message : 'Gagal membuka WhatsApp Web. Coba kembali.');
            form.dataset.submitting = '0';
            if (submitButton) submitButton.disabled = false;
        }
    });
})();
