/* TTE v6.17 consolidated asset: public/js/tte-ticket.js. Original module order preserved. */

/* ===== BEGIN public/js/ams-workflow.js ===== */
(() => {
    'use strict';
    const form = document.getElementById('ams-verification-form');
    if (!form) return;

    const fields = Array.from(form.querySelectorAll('[data-ams-field]'));
    const [activation, whatsapp, issuance, validity, alignment, recommendation] = fields;
    const summary = form.querySelector('.ams-summary');
    const decision = form.querySelector('#ams-decision-preview');
    const recap = form.querySelector('.ams-recap');
    const restart = form.querySelector('[data-ams-restart]');
    const requested = form.dataset.requestedService;
    const registration = requested === 'Pendaftaran';

    function showField(field, visible, required = visible) {
        field.hidden = !visible;
        field.disabled = !visible;
        for (const radio of field.querySelectorAll('input')) {
            radio.required = visible && required;
            if (!visible) radio.checked = false;
        }
        return field.querySelector(':checked')?.value || '';
    }

    function refresh() {
        const account = showField(activation, true);
        const wa = showField(whatsapp, account === 'activated');
        const se = showField(issuance, Boolean(wa));
        const status = showField(validity, se === 'issued');
        const verifiedActive = status === 'active' && wa === 'verified';
        const registrationMismatch =
            verifiedActive && registration && form.dataset.onboardingHistory !== '1';
        const match = showField(alignment, verifiedActive && !registration, false);
        const service = showField(recommendation, registrationMismatch || match === 'unsuitable');

        let next = 'Periksa status aktivasi akun pemohon di AMS.';
        let autoMatch = '';
        let autoService = '';
        if (account === 'not_activated') {
            next = registration
                ? 'Kirim Notifikasi 4 (Pendaftaran Diproses). Konfirmasi pengiriman menyelesaikan penanganan administratif. Pemohon tetap perlu mengaktifkan akun.'
                : 'Pandu pemohon mengaktifkan akun, lalu periksa ulang pada tiket ini.';
        } else if (account === 'activated' && !wa) {
            next = 'Periksa status verifikasi nomor WhatsApp di AMS.';
        } else if (wa && !se) {
            next = 'Periksa apakah Sertifikat Elektronik sudah terbit.';
        } else if (se === 'not_issued') {
            autoMatch = registration ? 'Sesuai' : 'Belum sesuai';
            next =
                'Arahkan pemohon untuk set passphrase melalui email BSrE, lalu periksa ulang pada tiket ini.';
        } else if (se === 'issued' && !status) {
            next = 'Periksa status sertifikat di AMS.';
        } else if (status === 'revoked') {
            autoMatch = 'Belum sesuai';
            autoService = 'Penerbitan Ulang';
            next =
                'Arahkan penerbitan ulang pada tiket ini dan periksa kembali setelah sertifikat terbit.';
        } else if (wa === 'unverified') {
            next =
                'Pandu verifikasi nomor WhatsApp, lalu periksa ulang di AMS sebelum menutup tiket.';
        } else if (status === 'expired') {
            const renewal = requested === 'Pembaruan';
            autoMatch = renewal ? 'Sesuai' : 'Belum sesuai';
            if (!renewal) autoService = 'Pembaruan Sertifikat Elektronik';
            next = renewal
                ? 'Proses pembaruan dan periksa ulang sampai sertifikat aktif.'
                : 'Sampaikan koreksi ke layanan Pembaruan dan konfirmasikan pengiriman sebelum mengembalikan permohonan.';
        } else if (!recommendation.hidden) {
            autoMatch = 'Belum sesuai';
            next = service
                ? 'Sampaikan koreksi layanan ke ' + service + ' dan konfirmasikan pengiriman.'
                : 'Klarifikasi kebutuhan pemohon, lalu pilih layanan yang disarankan.';
        } else if (verifiedActive) {
            if (form.dataset.continuationMismatch === '1') {
                autoMatch = 'Belum sesuai (lanjut pada tiket ini)';
            }
            next =
                form.dataset.issuanceNoticeConfirmed === '1'
                    ? 'SE kini aktif setelah pemberitahuan sebelumnya. Periksa hasil aktual sebelum menandai tiket selesai.'
                    : 'SE aktif dan WA terverifikasi. Simpan pemeriksaan dan ikuti tindakan berikutnya pada tiket.';
        }
        if (wa === 'unverified' && (se === 'not_issued' || status === 'revoked')) {
            next += ' Nomor WhatsApp juga perlu diverifikasi.';
        }

        decision.textContent = next;
        const rows = [];
        for (const field of fields) {
            const automatic =
                field === alignment ? autoMatch : field === recommendation ? autoService : '';
            if (field.hidden && !automatic) continue;
            const selected = field.querySelector('input:checked');
            const row = document.createElement('div');
            const term = document.createElement('dt');
            const value = document.createElement('dd');
            term.textContent = field.dataset.summaryLabel;
            value.textContent =
                automatic ||
                selected?.labels[0].textContent.trim() ||
                (field === alignment ? 'Otomatis saat disimpan' : 'Belum dipilih');
            row.append(term, value);
            rows.push(row);
        }
        recap.replaceChildren(...rows);
        summary.hidden = false;
        restart.hidden = !account && !form.elements.wa_verification_note.value;
    }

    form.addEventListener('change', (event) => {
        if (event.target.type !== 'radio') return;
        const index = fields.indexOf(event.target.closest('fieldset'));
        if (index < 0) return;
        for (const field of fields.slice(index + 1)) {
            for (const choice of field.querySelectorAll('input:checked')) choice.checked = false;
        }
        refresh();
    });
    form.elements.wa_verification_note.addEventListener('input', () => {
        restart.hidden =
            !activation.querySelector(':checked') && !form.elements.wa_verification_note.value;
    });
    restart.addEventListener('click', () => {
        for (const choice of form.querySelectorAll('input[type=radio]:checked'))
            choice.checked = false;
        form.elements.wa_verification_note.value = '';
        form.elements.wa_verification_note.dispatchEvent(new Event('input', { bubbles: true }));
        refresh();
        activation.querySelector('input').focus();
    });

    refresh();
    form.querySelector('button[type=submit]').disabled = false;
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
    let pendingMobileReturn = null;

    async function refreshConfirmation(form) {
        const state = document.getElementById(form.dataset.waState);
        if (!state || state.dataset.loading) return;
        state.dataset.loading = '1';
        try {
            const response = await fetch(window.location.href, {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { Accept: 'text/html' },
            });
            if (!response.ok || response.redirected) throw new Error();
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = page.getElementById(state.id);
            if (!updated || !updated.textContent.trim()) throw new Error();
            state.replaceChildren(...updated.childNodes);
            const next = page.querySelector('.tte-next-action');
            if (next) document.querySelector('.tte-next-action')?.replaceWith(next);
        } catch {
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'btn btn-outline';
            retry.textContent = 'Muat ulang status konfirmasi';
            retry.addEventListener('click', () => refreshConfirmation(form));
            state.replaceChildren('Status konfirmasi belum tersedia. ', retry);
        } finally {
            delete state.dataset.loading;
        }
    }

    function refreshOnReturn() {
        if (pendingMobileReturn && document.visibilityState === 'visible') {
            const form = pendingMobileReturn;
            pendingMobileReturn = null;
            refreshConfirmation(form);
        }
    }
    document.addEventListener('visibilitychange', refreshOnReturn);
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
            pendingMobileReturn = form;
        });
        const fallback = makeLink('Buka melalui browser', links.web, 'btn btn-outline full');
        fallback.target = '_blank';
        fallback.rel = 'noopener noreferrer';
        fallback.setAttribute('data-wa-browser-link', '1');
        fallback.addEventListener('click', () => {
            pendingMobileReturn = form;
        });
        const info = document.createElement('p');
        info.className = 'small muted';
        info.textContent =
            'Jika aplikasi tidak terbuka, gunakan opsi browser atau buka halaman ini di Safari/Chrome. Setelah mengirim pesan, kembali ke tiket dan konfirmasi secara manual.';
        const reload = document.createElement('button');
        reload.type = 'button';
        reload.className = 'btn btn-ghost full';
        reload.textContent = 'Kembali ke Tiket / Tampilkan Konfirmasi';
        reload.addEventListener('click', () => refreshConfirmation(form));

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
                await refreshConfirmation(form);
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

// Warn only when navigation would discard edits in a different, unsubmitted form.
(() => {
    const dirty = new Set();
    const warn = (event) => {
        event.preventDefault();
        event.returnValue = '';
    };
    function watch() {
        window.removeEventListener('beforeunload', warn);
        if (dirty.size) window.addEventListener('beforeunload', warn);
    }
    document.addEventListener('input', (event) => {
        const form = event.target.form;
        if (!form || !form.closest('.detail-columns')) return;
        dirty.add(form);
        watch();
    });
    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented || !dirty.delete(event.target)) return;
        watch();
        // Restore tracking if the user cancels navigation to keep another draft.
        setTimeout(() => {
            dirty.add(event.target);
            watch();
        }, 250);
    });
})();
