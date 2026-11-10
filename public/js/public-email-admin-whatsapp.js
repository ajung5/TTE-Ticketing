/* TTE v65: kontak pengelola email dari formulir publik.
 * Mobile: tautan native whatsapp:// hanya saat ketukan langsung oleh pengguna.
 * Browser fallback tetap tersedia tanpa perlu AJAX, login, atau form submission.
 * Desktop: WhatsApp Web. Tanpa JS: link wa.me dari Blade tetap berfungsi.
 */
(() => {
    'use strict';
    const primary = document.querySelector('[data-email-admin-wa="1"]');
    if (!primary) return;

    // Hanya tautan resmi dari konfigurasi server yang dapat menjadi deep link.
    let destination;
    try {
        destination = new URL(primary.href);
    } catch (_) {
        return;
    }
    if (
        destination.protocol !== 'https:' ||
        destination.hostname !== 'wa.me' ||
        destination.username ||
        destination.password ||
        destination.port ||
        destination.hash
    )
        return;
    const phone = destination.pathname.slice(1);
    const message = destination.searchParams.get('text');
    if (!/^628[0-9]{7,12}$/.test(phone) || !message || destination.pathname !== '/' + phone) return;

    const mobile =
        Boolean(navigator.userAgentData?.mobile) ||
        /Android|iPhone|iPad|iPod|IEMobile|Windows Phone|Opera Mini/i.test(
            navigator.userAgent || '',
        ) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    if (!mobile) {
        primary.href =
            'https://web.whatsapp.com/send?phone=' + phone + '&text=' + encodeURIComponent(message);
        primary.target = '_blank';
        primary.rel = 'noopener noreferrer';
        return;
    }

    // Anchor scheme harus ditap secara langsung. Jangan memanggilnya setelah await/timer:
    // Safari/iOS dan sebagian browser Android membatasi deep link tanpa user gesture.
    primary.href = 'whatsapp://send?phone=' + phone + '&text=' + encodeURIComponent(message);
    primary.textContent = 'Buka Aplikasi WhatsApp';
    primary.removeAttribute('target');
    primary.setAttribute(
        'aria-label',
        'Buka aplikasi WhatsApp untuk menghubungi Pengelola Email Dinas',
    );
    primary.dataset.waNativeLink = '1';

    const fallback = document.createElement('a');
    fallback.href = destination.href;
    fallback.textContent = 'Buka melalui browser';
    fallback.className = 'btn btn-outline';
    fallback.target = '_blank';
    fallback.rel = 'noopener noreferrer';
    fallback.dataset.waBrowserFallback = '1';
    primary.insertAdjacentElement('afterend', fallback);

    const notice = document.createElement('p');
    notice.className = 'small muted';
    notice.textContent =
        'Jika WhatsApp tidak terbuka, pilih Buka melalui browser. Pesan baru terkirim setelah Anda menekan Kirim di WhatsApp.';
    notice.style.flexBasis = '100%';
    notice.style.margin = '2px 0 0';
    fallback.insertAdjacentElement('afterend', notice);
})();
