/* TTE v6.17 consolidated asset: public/js/tte-ticket.js. Original module order preserved. */

/* ===== BEGIN public/js/ams-workflow.js ===== */
/* AMS v35: aktivasi -> WA -> penerbitan -> Aktif/Expired/Revoke -> kesesuaian layanan. */
(() => {
    'use strict';
    const form = document.getElementById('ams-verification-form');
    if (!form) return;
    const activation = form.querySelector('[name="activation_status"]');
    const wa = form.querySelector('[name="wa_verification_status"]');
    const cert = form.querySelector('[name="certificate_issuance_status"]');
    const validity = form.querySelector('[name="certificate_validity_status"]');
    const recommended = form.querySelector('[name="recommended_service"]');
    const waBlock = document.getElementById('ams-wa-block');
    const certBlock = document.getElementById('ams-certificate-block');
    const validityBlock = document.getElementById('ams-validity-block');
    const recommendationBlock = document.getElementById('ams-recommendation-block');
    const indicator = document.getElementById('ams-decision-preview');
    const requested = form.dataset.requestedService;
    const existingService = ['Reset Passphrase', 'Pembaruan'].includes(requested);
    const hasOnboardingHistory = form.dataset.onboardingHistory === '1';
    const noticeConfirmed = form.dataset.issuanceNoticeConfirmed === '1';

    // Frontend progresif: pilihan Revoke ditambah tanpa mengganti markup verifikator lama.
    if (validity && !Array.from(validity.options).some((o) => o.value === 'revoked')) {
        validity.add(new Option('Dicabut / Revoke', 'revoked'));
        const summary = document.querySelector('.verification-status');
        if (summary && summary.textContent.includes('Revoke')) validity.value = 'revoked';
    }
    let alignment = form.querySelector('[name="service_alignment_status"]');
    let alignmentBlock = null;
    if (!alignment) {
        alignmentBlock = document.createElement('div');
        alignmentBlock.id = 'ams-service-alignment-block';
        alignmentBlock.className = 'ams-conditional';
        alignmentBlock.hidden = true;
        const label = document.createElement('label');
        label.setAttribute('for', 'ams-service-alignment');
        label.textContent = '5. Kesesuaian layanan yang diajukan';
        alignment = document.createElement('select');
        alignment.id = 'ams-service-alignment';
        alignment.name = 'service_alignment_status';
        alignment.add(new Option('Tentukan kesesuaian layanan', ''));
        alignment.add(new Option('Sesuai', 'suitable'));
        alignment.add(new Option('Belum sesuai', 'unsuitable'));
        label.appendChild(alignment);
        alignmentBlock.appendChild(label);
        const note = document.createElement('p');
        note.className = 'small muted';
        note.textContent =
            'Tahapan sementara (aktivasi, set passphrase, atau penerbitan ulang) boleh tetap memakai tiket yang sama meskipun layanan awal berbeda. Kondisi salah layanan yang tidak dapat dilanjutkan akan memerlukan klarifikasi dan rekomendasi.';
        alignmentBlock.appendChild(note);
        const before = recommendationBlock || indicator;
        before.parentNode.insertBefore(alignmentBlock, before);
    } else {
        alignmentBlock = alignment.closest('.ams-conditional');
    }

    function stage(element, block, show, required = show) {
        if (!element || !block) return;
        block.hidden = !show;
        element.disabled = !show;
        element.required = required;
    }
    function refresh() {
        const activated = activation.value === 'activated';
        stage(wa, waBlock, activated);
        const hasWa = activated && ['verified', 'unverified'].includes(wa.value);
        stage(cert, certBlock, hasWa);
        const issued = hasWa && cert.value === 'issued';
        stage(validity, validityBlock, issued);
        const verified = hasWa && wa.value === 'verified';
        const expired = issued && validity.value === 'expired';
        const revoked = issued && validity.value === 'revoked';
        const active = issued && validity.value === 'active';
        const notIssued = hasWa && cert.value === 'not_issued';
        const registrationMismatch =
            verified && active && requested === 'Pendaftaran' && !hasOnboardingHistory;
        const canRecommend = registrationMismatch || (verified && active && existingService);
        stage(alignment, alignmentBlock, issued && verified && !revoked, false);
        stage(
            recommended,
            recommendationBlock,
            canRecommend,
            registrationMismatch || alignment.value === 'unsuitable',
        );
        for (const option of recommended.options) {
            option.disabled = option.value !== '' && option.value === requested;
        }
        if (!canRecommend || recommended.value === requested) recommended.value = '';
        if (!issued || revoked) alignment.value = '';
        if (registrationMismatch || (expired && requested !== 'Pembaruan'))
            alignment.value = 'unsuitable';
        // Keep manual choice only for ambiguous cases. The server still decides.
        if (expired && requested !== 'Pembaruan') {
            stage(recommended, recommendationBlock, false);
            recommended.value = '';
        }

        let message = 'Mulai dari pemeriksaan aktivasi akun.';
        if (activation.value === 'not_activated') {
            message =
                requested === 'Pendaftaran'
                    ? 'Pendaftaran Diproses: kirim Notifikasi 4. Setelah pengiriman dikonfirmasi, tiket otomatis Selesai dan survei tersedia. Aktivasi akun tetap menjadi tindak lanjut pemohon.'
                    : 'Akun belum aktivasi. Pandu aktivasi dan periksa ulang menggunakan tiket yang sama.';
        } else if (activated && !hasWa) {
            message = 'Tahap 2: periksa verifikasi nomor WA di AMS BSrE.';
        } else if (hasWa && !cert.value) {
            message = 'Tahap 3: periksa apakah Sertifikat Elektronik sudah pernah diterbitkan.';
        } else if (issued && !validity.value) {
            message = 'Tahap 4: periksa status sertifikat: Aktif, Expired, atau Revoke.';
        } else if (notIssued) {
            message =
                'SE belum terbit. Lanjutkan set passphrase melalui email BSrE pada tiket yang sama.' +
                (wa.value === 'unverified'
                    ? ' Nomor WA juga harus diverifikasi sebelum penutupan.'
                    : '') +
                ' Periksa ulang AMS setelah proses.';
        } else if (revoked) {
            message =
                'SE berstatus Revoke. Arahkan Penerbitan Ulang melalui mekanisme resmi BSrE. Tetap gunakan nomor tiket ini, lalu periksa ulang sampai SE aktif.' +
                (wa.value === 'unverified' ? ' Verifikasi WA juga wajib diselesaikan.' : '');
        } else if (hasWa && wa.value === 'unverified') {
            message =
                'WA belum terverifikasi. Status SE tetap dicatat; verifikasi WA harus selesai sebelum penutupan.';
        } else if (expired && verified) {
            message =
                requested === 'Pembaruan'
                    ? 'SE kedaluwarsa. Proses pembaruan dan periksa ulang sampai sertifikat aktif.'
                    : 'SE kedaluwarsa. Rekomendasikan Pembaruan Sertifikat Elektronik.';
        } else if (registrationMismatch) {
            message =
                'SE sudah aktif sejak sebelum Pendaftaran. Klarifikasi, lalu tentukan layanan yang benar.';
        } else if (active && verified && existingService && recommended.value) {
            message =
                'Layanan berbeda setelah klarifikasi: ' +
                recommended.value +
                '. Kirim notifikasi koreksi.';
        } else if (active && verified && noticeConfirmed) {
            message =
                'SE kini aktif setelah pemberitahuan sebelumnya. Periksa hasil aktual sebelum Tandai Selesai.';
        } else if (active && verified) {
            message =
                'SE aktif dan WA terverifikasi. Konfirmasi penyelesaian proses yang benar-benar dilakukan.';
        }
        indicator.textContent = message;
    }
    for (const element of [activation, wa, cert, validity, recommended, alignment]) {
        element.addEventListener('change', refresh);
    }
    refresh();
})();
/* ===== END public/js/ams-workflow.js ===== */

/* ===== BEGIN public/js/whatsapp-open-refresh.js ===== */
/* TTE v57: native WhatsApp needs a DIRECT TAP on a deep link in mobile browsers.
 * Record opened_at through an authorized POST, then show a user-clickable
 * whatsapp:// link (and wa.me fallback). Never count opening as delivery.
 * Desktop retains WhatsApp Web in a separate named tab.
 */
(() => {
    'use strict';

    const mobile =
        Boolean(navigator.userAgentData?.mobile) ||
        /Android|iPhone|iPad|iPod|IEMobile|Windows Phone|Opera Mini/i.test(
            navigator.userAgent || '',
        ) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    let pendingMobileReturn = false;
    let mobileWentBackground = false;

    const refreshOnReturn = () => {
        if (pendingMobileReturn && mobileWentBackground && document.visibilityState === 'visible') {
            pendingMobileReturn = false;
            window.location.reload();
        }
    };
    document.addEventListener('visibilitychange', () => {
        if (pendingMobileReturn && document.visibilityState === 'hidden')
            mobileWentBackground = true;
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
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) throw new Error(`Permintaan gagal (${response.status}).`);
        const result = await response.json();
        if (typeof result.url !== 'string') throw new Error('Tautan WhatsApp tidak tersedia.');
        return new URL(result.url);
    };

    const mobileLinks = (destination) => {
        // Never turn arbitrary server URLs into an application deep link.
        if (
            destination.protocol !== 'https:' ||
            destination.hostname !== 'wa.me' ||
            destination.username ||
            destination.password ||
            destination.port ||
            destination.hash
        ) {
            throw new Error('Tautan WhatsApp mobile dari server tidak valid.');
        }
        const phone = destination.pathname.replace(/^\//, '');
        const message = destination.searchParams.get('text');
        if (!/^62[0-9]{8,13}$/.test(phone) || !message) {
            throw new Error('Nomor atau pesan WhatsApp tidak valid.');
        }
        return {
            native: `whatsapp://send?phone=${phone}&text=${encodeURIComponent(message)}`,
            web: destination.href,
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
        explanation.textContent =
            'Ketuk tombol berikut untuk membuka aplikasi WhatsApp di handphone. Pesan belum dianggap terkirim.';
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
        info.textContent =
            'Jika aplikasi tidak terbuka, gunakan opsi browser atau buka halaman ini di Safari/Chrome. Setelah mengirim pesan, kembali ke tiket dan konfirmasi secara manual.';
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
                window.alert(
                    'Tab WhatsApp diblokir browser. Izinkan pop-up untuk situs ini lalu coba kembali.',
                );
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
                if (
                    destination.protocol !== 'https:' ||
                    destination.hostname !== 'web.whatsapp.com' ||
                    destination.username ||
                    destination.password
                ) {
                    throw new Error('Tautan WhatsApp Web dari server tidak valid.');
                }
                waTab.opener = null;
                waTab.location.replace(destination.href);
                // opened_at has already been persisted; enable the confirm button on reload.
                window.location.reload();
            }
        } catch (error) {
            if (waTab) waTab.close();
            window.alert(
                error instanceof Error ? error.message : 'Gagal membuka WhatsApp. Coba kembali.',
            );
        } finally {
            form.dataset.submitting = '0';
            if (button) button.disabled = false;
        }
    });
})();
/* ===== END public/js/whatsapp-open-refresh.js ===== */
