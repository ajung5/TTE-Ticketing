/* TTE v57: native WhatsApp needs a DIRECT TAP on a deep link in mobile browsers.
 * Record opened_at through an authorized POST, then show a user-clickable
 * whatsapp:// link (and wa.me fallback). Never count opening as delivery.
 * Desktop retains WhatsApp Web in a separate named tab.
 */
(() => {
    'use strict';

    const mobile = Boolean(navigator.userAgentData?.mobile)
        || /Android|iPhone|iPad|iPod|IEMobile|Windows Phone|Opera Mini/i.test(navigator.userAgent || '')
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    let pendingMobileReturn = false;
    let mobileWentBackground = false;

    const refreshOnReturn = () => {
        if (pendingMobileReturn && mobileWentBackground && document.visibilityState === 'visible') {
            pendingMobileReturn = false;
            window.location.reload();
        }
    };
    document.addEventListener('visibilitychange', () => {
        if (pendingMobileReturn && document.visibilityState === 'hidden') mobileWentBackground = true;
        refreshOnReturn();
    });
    window.addEventListener('pageshow', refreshOnReturn);
    window.addEventListener('focus', refreshOnReturn);

    const recordOpen = async (form, isMobile) => {
        const data = new FormData(form);
        if (isMobile) data.set('wa_client', 'mobile');
        const response = await fetch(form.action, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        if (!response.ok) throw new Error(`Permintaan gagal (${response.status}).`);
        const result = await response.json();
        if (typeof result.url !== 'string') throw new Error('Tautan WhatsApp tidak tersedia.');
        return new URL(result.url);
    };

    const mobileLinks = (destination) => {
        // Never turn arbitrary server URLs into an application deep link.
        if (destination.protocol !== 'https:' || destination.hostname !== 'wa.me'
            || destination.username || destination.password || destination.port || destination.hash) {
            throw new Error('Tautan WhatsApp mobile dari server tidak valid.');
        }
        const phone = destination.pathname.replace(/^\//, '');
        const message = destination.searchParams.get('text');
        if (!/^62[0-9]{8,13}$/.test(phone) || !message) {
            throw new Error('Nomor atau pesan WhatsApp tidak valid.');
        }
        return {
            native: `whatsapp://send?phone=${phone}&text=${encodeURIComponent(message)}`,
            web: destination.href
        };
    };

    const makeLink = (text, href, className) => {
        const a = document.createElement('a');
        a.href = href;
        a.textContent = text;
        a.className = className;
        return a;
    };

    const showMobileHandoff = (form, links) => {
        // The two-step handoff is intentional: iOS/Android may reject app opening
        // after an async fetch or a server-side redirect without a fresh user tap.
        form.hidden = true;
        form.parentElement?.querySelector('.tte-wa-native-panel')?.remove();
        const panel = document.createElement('div');
        panel.className = 'tte-wa-native-panel note-box';
        panel.setAttribute('role', 'status');
        panel.setAttribute('data-wa-native-panel', '1');

        const title = document.createElement('strong');
        title.textContent = 'Pesan siap dibuka di WhatsApp';
        const explanation = document.createElement('p');
        explanation.textContent = 'Ketuk tombol berikut untuk membuka aplikasi WhatsApp di handphone. Pesan belum dianggap terkirim.';
        const native = makeLink('Buka Aplikasi WhatsApp', links.native, 'btn btn-wa full');
        native.setAttribute('data-wa-native-link', '1');
        native.addEventListener('click', () => {
            pendingMobileReturn = true;
            mobileWentBackground = false;
        });
        const fallback = makeLink('Buka melalui browser', links.web, 'btn btn-outline full');
        fallback.target = '_blank';
        fallback.rel = 'noopener noreferrer';
        fallback.setAttribute('data-wa-browser-link', '1');
        fallback.addEventListener('click', () => {
            pendingMobileReturn = true;
            mobileWentBackground = false;
        });
        const info = document.createElement('p');
        info.className = 'small muted';
        info.textContent = 'Jika aplikasi tidak terbuka, gunakan opsi browser atau buka halaman ini di Safari/Chrome. Setelah mengirim pesan, kembali ke tiket dan konfirmasi secara manual.';
        const reload = document.createElement('button');
        reload.type = 'button';
        reload.className = 'btn btn-ghost full';
        reload.textContent = 'Kembali ke Tiket / Tampilkan Konfirmasi';
        reload.addEventListener('click', () => window.location.reload());

        panel.append(title, explanation, native, fallback, info, reload);
        form.insertAdjacentElement('afterend', panel);
        panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    };

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.waOpen !== '1') return;
        event.preventDefault();
        if (form.dataset.submitting === '1') return;

        const button = form.querySelector('button[type="submit"]');
        let waTab = null;
        if (!mobile) {
            // Preserve user gesture for desktop popup blockers.
            waTab = window.open('about:blank', form.getAttribute('target') || '_blank');
            if (!waTab) {
                window.alert('Tab WhatsApp diblokir browser. Izinkan pop-up untuk situs ini lalu coba kembali.');
                return;
            }
        }
        form.dataset.submitting = '1';
        if (button) button.disabled = true;

        try {
            const destination = await recordOpen(form, mobile);
            if (mobile) {
                showMobileHandoff(form, mobileLinks(destination));
                // IMPORTANT: Do not auto-navigate to whatsapp:// after await.
                // The explicit anchor tap is what allows the native app to open.
            } else {
                if (destination.protocol !== 'https:' || destination.hostname !== 'web.whatsapp.com'
                    || destination.username || destination.password) {
                    throw new Error('Tautan WhatsApp Web dari server tidak valid.');
                }
                waTab.opener = null;
                waTab.location.replace(destination.href);
                // opened_at has already been persisted; enable the confirm button on reload.
                window.location.reload();
            }
        } catch (error) {
            if (waTab) waTab.close();
            window.alert(error instanceof Error ? error.message : 'Gagal membuka WhatsApp. Coba kembali.');
        } finally {
            form.dataset.submitting = '0';
            if (button) button.disabled = false;
        }
    });
})();
