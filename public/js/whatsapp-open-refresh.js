/* TTE v56: desktop WhatsApp Web; mobile official WhatsApp universal links.
 * Both paths must POST to Laravel first, so opened_at is recorded before navigation.
 * Opening WhatsApp never confirms delivery; the verifier confirms manually.
 */
(() => {
    'use strict';

    const mobile = Boolean(navigator.userAgentData?.mobile)
        || /Android|iPhone|iPad|iPod|IEMobile|Windows Phone|Opera Mini/i.test(navigator.userAgent || '')
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const pendingKey = 'tte-wa-native-open-pending';
    let pendingMobileReturn = false;
    let submittedAt = 0;

    const reloadAfterMobileReturn = () => {
        if (!pendingMobileReturn || document.visibilityState === 'hidden') return;
        pendingMobileReturn = false;
        try { sessionStorage.removeItem(pendingKey); } catch (_) { /* Private browsing. */ }
        window.location.reload();
    };
    const scheduleReturnRefresh = () => {
        if (!pendingMobileReturn || document.visibilityState === 'hidden') return;
        // Avoid reloading before the server has recorded opened_at.
        const remaining = Math.max(0, 1300 - (Date.now() - submittedAt));
        window.setTimeout(reloadAfterMobileReturn, remaining);
    };
    document.addEventListener('visibilitychange', scheduleReturnRefresh);
    window.addEventListener('pageshow', scheduleReturnRefresh);
    window.addEventListener('focus', scheduleReturnRefresh);

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.waOpen !== '1') return;
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }

        if (mobile) {
            // Keep the native user-initiated form submission for iOS/Android.
            // A POST in a new tab redirects to https://wa.me/... after authorization
            // and opened_at persistence; wa.me is an official universal/app link.
            // An async window.open() of a custom URI can be blocked on mobile.
            let hint = form.querySelector('input[name="wa_client"]');
            if (!hint) {
                hint = document.createElement('input');
                hint.type = 'hidden';
                hint.name = 'wa_client';
                form.appendChild(hint);
            }
            hint.value = 'mobile';
            form.dataset.submitting = '1';
            pendingMobileReturn = true;
            submittedAt = Date.now();
            try { sessionStorage.setItem(pendingKey, '1'); } catch (_) { /* Optional. */ }
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            // Browser submits normally, preserving the click gesture for native WhatsApp.
            // If a browser leaves this tab visible, refresh once as a fallback.
            window.setTimeout(scheduleReturnRefresh, 2100);
            return;
        }

        // Desktop keeps the existing async flow: open a tab synchronously,
        // authorize/record the open via Laravel, then navigate to WhatsApp Web.
        event.preventDefault();
        const targetName = form.getAttribute('target') || '_blank';
        const waTab = window.open('about:blank', targetName);
        if (!waTab) {
            window.alert('Tab WhatsApp diblokir browser. Izinkan pop-up untuk situs ini lalu coba kembali.');
            return;
        }
        form.dataset.submitting = '1';
        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;

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
            if (destination.protocol !== 'https:' || destination.hostname !== 'web.whatsapp.com'
                || destination.username || destination.password) {
                throw new Error('Alamat WhatsApp Web dari server tidak valid.');
            }
            waTab.opener = null;
            waTab.location.replace(destination.href);
            // The original tab reloads only after opened_at was stored.
            window.location.reload();
        } catch (error) {
            waTab.close();
            window.alert(error instanceof Error ? error.message : 'Gagal membuka WhatsApp. Coba kembali.');
            form.dataset.submitting = '0';
            if (button) button.disabled = false;
        }
    });
})();
