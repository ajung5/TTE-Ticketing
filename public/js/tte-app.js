/* TTE v6.17 consolidated asset: public/js/tte-app.js. Original module order preserved. */

/* ===== BEGIN public/js/tte-internal-refresh.js ===== */
/* TTE internal UI v47-v49. No business rules or network activity. */
(() => {
    'use strict';
    const root = document.querySelector('.tte-internal');
    if (!root) return;
    const sidebar = root.querySelector('.sidebar');
    const toggle = sidebar?.querySelector('[data-menu-toggle]');
    if (sidebar && toggle) {
        sidebar.classList.add('ui-menu-ready');
        const setOpen = (value) => {
            sidebar.classList.toggle('ui-menu-open', value);
            toggle.setAttribute('aria-expanded', String(value));
        };
        toggle.addEventListener('click', () =>
            setOpen(!sidebar.classList.contains('ui-menu-open')),
        );
        // Reset menu state when returning to desktop breakpoint.
        window.matchMedia('(min-width: 681px)').addEventListener('change', (e) => {
            if (e.matches) setOpen(false);
        });
    }

    const openDialog = (dialog, trigger) => {
        if (!dialog) return;
        if (typeof dialog.showModal === 'function') {
            if (!dialog.open) dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
        dialog.__returnFocus = trigger || null;
    };
    document.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-open-dialog]');
        if (opener) {
            openDialog(document.getElementById(opener.dataset.openDialog), opener);
            return;
        }
        const closer = e.target.closest('[data-close-dialog]');
        if (closer) {
            const dialog = closer.closest('dialog');
            if (dialog)
                typeof dialog.close === 'function'
                    ? dialog.close()
                    : dialog.removeAttribute('open');
        }
    });
    for (const dialog of root.querySelectorAll('.tte-verifier-dialog')) {
        dialog.addEventListener('close', () => dialog.__returnFocus?.focus());
        if (dialog.hasAttribute('data-auto-open')) openDialog(dialog, null);
    }

    // Only one preset editor should be expanded, including browsers without details[name] grouping.
    const presets = [...root.querySelectorAll('.tte-preset-detail')];
    for (const preset of presets) {
        preset.addEventListener('toggle', () => {
            if (preset.open)
                presets.forEach((other) => {
                    if (other !== preset) other.open = false;
                });
        });
    }
})();

/* TTE v51: persisted desktop sidebar toggle (independent from mobile menu). */
(() => {
    'use strict';
    const root = document.querySelector('.tte-internal');
    const toggle = root?.querySelector('[data-sidebar-toggle]');
    if (!root || !toggle) return;
    const storageKey = 'tte-sidebar-collapsed-v1';
    let collapsed = false;
    try {
        collapsed = window.localStorage.getItem(storageKey) === '1';
    } catch (_) {
        /* Storage can be unavailable in private browsing. */
    }
    const render = () => {
        root.classList.toggle('tte-sidebar-collapsed', collapsed);
        toggle.setAttribute('aria-pressed', String(collapsed));
        const label = collapsed ? 'Perbesar menu samping' : 'Perkecil menu samping';
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('title', label);
        // Accessible names remain as text in each link even when visually icon-only.
    };
    for (const link of root.querySelectorAll('.sidebar .nav-item')) {
        if (!link.hasAttribute('title')) link.title = link.textContent.trim().replace(/\s+/g, ' ');
    }
    const logout = root.querySelector('.sidebar-logout');
    if (logout && !logout.hasAttribute('title')) logout.title = 'Keluar Sistem';
    render();
    root.classList.add('tte-sidebar-ready');
    toggle.addEventListener('click', () => {
        collapsed = !collapsed;
        render();
        try {
            window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
        } catch (_) {
            /* Keep working without persistence. */
        }
    });
})();
/* ===== END public/js/tte-internal-refresh.js ===== */

/* ===== BEGIN public/js/tte-back-to-top.js ===== */
/* TTE v59: small-screen return-to-top action, only near page end.
 * Passive scroll listening; no impact on desktop or workflow state.
 */
(() => {
    'use strict';
    const button = document.querySelector('[data-tte-back-top]');
    if (!button) return;
    const mobileQuery = window.matchMedia('(max-width: 680px)');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let scheduled = false;
    const update = () => {
        scheduled = false;
        const root = document.documentElement;
        const documentHeight = Math.max(root.scrollHeight, document.body.scrollHeight);
        const scrollPosition = window.scrollY || root.scrollTop || 0;
        const nearBottom = documentHeight - (scrollPosition + window.innerHeight) <= 180;
        button.hidden = !(mobileQuery.matches && scrollPosition > 280 && nearBottom);
    };
    const queueUpdate = () => {
        if (scheduled) return;
        scheduled = true;
        window.requestAnimationFrame(update);
    };
    button.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: reducedMotion.matches ? 'auto' : 'smooth' });
        button.hidden = true;
    });
    window.addEventListener('scroll', queueUpdate, { passive: true });
    window.addEventListener('resize', queueUpdate, { passive: true });
    window.addEventListener('pageshow', queueUpdate);
    if (mobileQuery.addEventListener) mobileQuery.addEventListener('change', queueUpdate);
    else mobileQuery.addListener(queueUpdate);
    update();
})();
/* ===== END public/js/tte-back-to-top.js ===== */

/* ===== BEGIN public/js/tte.js ===== */
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
        // A token in an inline guide must always target that same preset.
        const localEditor = button
            .closest('.tte-preset-detail')
            ?.querySelector('.template-textarea');
        const activeEditor = focusedPresetEditor?.closest('details')?.open
            ? focusedPresetEditor
            : null;
        const textarea =
            localEditor ||
            activeEditor ||
            document.querySelector('.tte-preset-detail[open] .template-textarea') ||
            document.querySelector('.template-textarea');
        if (!textarea || !token) return;
        const start = textarea.selectionStart,
            end = textarea.selectionEnd;
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
            setTimeout(() => {
                button.textContent = original;
                button.disabled = false;
            }, 1800);
        }
    });
})();
/* ===== END public/js/tte.js ===== */

/* ===== BEGIN public/js/tte-admin-period-v614.js ===== */
/* TTE v6.14: reveal/require custom dates only when the admin selects them. */
(() => {
    'use strict';
    const period = document.getElementById('tte-ux-period');
    const group = document.getElementById('tte-ux-period-custom-fields');
    const start = document.getElementById('tte-ux-date-from');
    const end = document.getElementById('tte-ux-date-to');
    if (!period || !group || !start || !end) return;

    const update = () => {
        const custom = period.value === 'custom';
        group.hidden = !custom;
        for (const input of [start, end]) {
            input.required = custom;
            input.disabled = !custom;
        }
        start.max = end.value || '';
        end.min = start.value || '';
    };
    period.addEventListener('change', update);
    start.addEventListener('change', update);
    end.addEventListener('change', update);
    update();
})();
/* ===== END public/js/tte-admin-period-v614.js ===== */

/* ===== BEGIN public/js/tte-preset-preview.js ===== */
/* TTE v58: safe preview using synthetic examples only. No staff/personal data. */
(() => {
    'use strict';
    const samples = {
        nama_pemohon: 'Bapak/Ibu Contoh',
        nomor_tiket: 'TTE-20261105-000123',
        nama_verifikator: 'Petugas Contoh',
        inisial_verifikator: 'PC',
        nama_instansi: 'Layanan TTE',
        opd: 'OPD Contoh',
        email: 'contoh@subang.go.id',
        nik: '32**********0001',
        nip: '198901012020011001',
        nomor_wa: '6281234567890',
        jabatan: 'Pegawai Contoh',
        keterangan: '-',
        layanan: 'Reset Passphrase',
        layanan_diajukan: 'Reset Passphrase',
        layanan_disarankan: 'Pembaruan Sertifikat Elektronik',
        proses_aktual: 'Pemeriksaan Sertifikat Elektronik',
        hasil_aktual: 'Layanan selesai',
        status_verifikasi_wa: 'Terverifikasi',
        form_url: 'https://contoh.invalid/permohonan',
        survey_url: 'https://contoh.invalid/survei/123?signature=CONTOH',
    };
    document.querySelectorAll('.tte-preset-detail').forEach((details) => {
        const editor = details.querySelector('textarea[name="body"]');
        const preview = details.querySelector('[data-tte-preset-preview]');
        if (!editor || !preview) return;
        const update = () => {
            preview.textContent = editor.value.replace(/\{([a-z_]+)\}/g, (whole, key) =>
                Object.prototype.hasOwnProperty.call(samples, key) ? samples[key] : whole,
            );
        };
        editor.addEventListener('input', update);
        details.addEventListener('toggle', () => {
            if (details.open) update();
        });
        update();
    });
})();
/* ===== END public/js/tte-preset-preview.js ===== */
