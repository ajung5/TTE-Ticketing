/* v15 public form: NIK/NIP directory autofill, editable profile, immutable full email, touch signature. */
(() => {
    'use strict';
    const form = document.getElementById('tte-public-form');
    if (!form) return;

    const nik = document.getElementById('nik');
    const layanan = document.getElementById('layanan');
    const whatsapp = document.getElementById('no_wa');
    const email = document.getElementById('email_display');
    const uptdField = document.getElementById('uptd-field');
    const uptdDisplay = document.getElementById('uptd_display');
    const nikStatus = document.getElementById('nik-status');
    const details = document.getElementById('request-details');
    const pending = document.getElementById('request-details-pending');
    const submit = document.getElementById('public-submit');
    const modal = document.getElementById('email-help-modal');
    const modalClose = document.getElementById('email-modal-close');
    const helpTrigger = document.getElementById('email-help-trigger');
    const canvas = document.getElementById('signature-pad');
    const signatureInput = document.getElementById('signature_data');
    const signatureStatus = document.getElementById('signature-status');
    const consent = form.querySelector('[name="persetujuan"]');
    const profileKeys = ['nama_user', 'nip', 'jabatan', 'opd'];
    const profileInputs = Object.fromEntries(
        profileKeys.map((key) => [key, document.getElementById(key)]),
    );
    const ctx = canvas.getContext('2d');
    let matched = false;
    let signed = false;
    let drawing = false;
    let debounce;
    let controller;
    let sequence = 0;
    let wasRestored =
        [16, 18].includes(nik.value.length) &&
        profileKeys.some((key) => profileInputs[key].value.trim());

    ctx.strokeStyle = '#173759';
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    function refreshSubmit() {
        submit.disabled =
            !matched || !signed || !consent.checked || !layanan.value || !whatsapp.checkValidity();
    }
    function setMatch(value) {
        matched = value;
        details.hidden = !value;
        pending.hidden = value;
        refreshSubmit();
    }
    let modalReturnFocus = nik;
    function openModal() {
        modalReturnFocus =
            document.activeElement instanceof HTMLElement ? document.activeElement : nik;
        modal.hidden = false;
        modal.querySelector('.directory-modal-body').focus();
    }
    function closeModal() {
        modal.hidden = true;
        (modalReturnFocus?.isConnected ? modalReturnFocus : nik).focus();
    }
    modalClose.addEventListener('click', closeModal);
    modal.querySelector('[data-close-directory-modal]').addEventListener('click', closeModal);
    helpTrigger.addEventListener('click', openModal);
    // UX-V613: trap focus inside the public help dialog, including Shift+Tab.
    document.addEventListener('keydown', (e) => {
        if (modal.hidden) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            closeModal();
            return;
        }
        if (e.key === 'Tab') {
            const focusable = [...modal.querySelectorAll('a[href], button:not([disabled])')].filter(
                (element) => element.getClientRects().length > 0,
            );
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (!first) {
                e.preventDefault();
                modal.querySelector('.directory-modal-body').focus();
            } else if (
                e.shiftKey &&
                (document.activeElement === first ||
                    document.activeElement === modal.querySelector('.directory-modal-body'))
            ) {
                e.preventDefault();
                last.focus();
            } else if (
                !e.shiftKey &&
                (document.activeElement === last || !modal.contains(document.activeElement))
            ) {
                e.preventDefault();
                first.focus();
            }
        }
    });
    consent.addEventListener('change', refreshSubmit);
    layanan.addEventListener('change', refreshSubmit);

    // Field memakai nomor lokal (8...), sedangkan +62 ditampilkan secara terpisah.
    // Nilai lama dan nomor yang ditempel dengan awalan 08/628 tetap ditangani.
    function localWhatsApp(value) {
        const digits = value.replace(/\D/g, '');
        const local = digits.startsWith('628')
            ? digits.slice(2)
            : digits.startsWith('08')
              ? digits.slice(1)
              : digits;
        return local.startsWith('8') ? local.slice(0, 13) : '';
    }
    whatsapp.value = localWhatsApp(whatsapp.value);
    whatsapp.addEventListener('input', () => {
        whatsapp.value = localWhatsApp(whatsapp.value);
        refreshSubmit();
    });

    function setUptd(value) {
        const name = typeof value === 'string' ? value.trim() : '';
        uptdDisplay.value = name === '-' ? '' : name;
        uptdField.hidden = uptdDisplay.value === '';
    }

    function setProfile(person, preserveExisting) {
        setUptd(person.uptd);
        for (const key of profileKeys) {
            // Old input has priority only after a server-side validation error.
            if (preserveExisting && profileInputs[key].value.trim()) continue;
            profileInputs[key].value = typeof person[key] === 'string' ? person[key] : '';
        }
        // No editable or named email field is submitted. The backend resolves it from the identifier again.
        email.value = person.email || '';
    }

    function lookupNik(preserveExisting = false) {
        clearTimeout(debounce);
        controller?.abort();
        const currentSequence = ++sequence;
        const currentNik = nik.value;
        setMatch(false);
        helpTrigger.hidden = true;
        email.value = '';
        setUptd('');
        nikStatus.textContent = [16, 18].includes(currentNik.length)
            ? 'Memeriksa data...'
            : 'Masukkan NIK/NIP untuk mencari data.';
        if (![16, 18].includes(currentNik.length)) return;

        debounce = setTimeout(
            async () => {
                controller = new AbortController();
                try {
                    const response = await fetch(form.dataset.lookupUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                        },
                        body: JSON.stringify({ nik: currentNik }),
                        signal: controller.signal,
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });
                    const result = await response.json();
                    if (currentSequence !== sequence || currentNik !== nik.value) return;

                    if (response.ok && result.status === 'found' && result.email) {
                        setProfile(result, preserveExisting);
                        nikStatus.textContent = 'Data pegawai ditemukan.';
                        setMatch(true);
                    } else if (result.status === 'not_found') {
                        helpTrigger.hidden = false;
                        nikStatus.textContent =
                            currentNik.length === 16
                                ? 'NIK tidak ditemukan. Coba gunakan NIP 18 digit.'
                                : 'NIK/NIP atau username Email Gov tidak tersedia.';
                        if (currentNik.length === 18) openModal();
                    } else if (response.status === 429) {
                        nikStatus.textContent =
                            'Terlalu banyak pencarian. Silakan coba beberapa saat lagi.';
                    } else {
                        nikStatus.textContent =
                            result.message || 'Direktori belum bisa diperiksa. Silakan coba lagi.';
                    }
                } catch (e) {
                    if (e.name !== 'AbortError' && currentSequence === sequence) {
                        nikStatus.textContent =
                            'Tidak dapat terhubung ke direktori. Silakan coba lagi.';
                    }
                } finally {
                    if (currentSequence === sequence) refreshSubmit();
                }
            },
            preserveExisting ? 100 : 450,
        );
    }

    nik.addEventListener('input', () => {
        nik.value = nik.value.replace(/\D/g, '').slice(0, 18);
        for (const key of profileKeys) profileInputs[key].value = '';
        wasRestored = false;
        if (!modal.hidden) modal.hidden = true;
        lookupNik(false);
    });

    const point = (e) => {
        const rect = canvas.getBoundingClientRect();
        return {
            x: ((e.clientX - rect.left) * canvas.width) / rect.width,
            y: ((e.clientY - rect.top) * canvas.height) / rect.height,
        };
    };
    canvas.addEventListener('pointerdown', (e) => {
        if (!e.isPrimary) return;
        drawing = true;
        canvas.setPointerCapture(e.pointerId);
        const p = point(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    });
    canvas.addEventListener('pointermove', (e) => {
        if (!drawing || !e.isPrimary) return;
        const p = point(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        signed = true;
        signatureStatus.textContent = 'Tanda tangan telah diisi.';
        refreshSubmit();
    });
    const endStroke = () => {
        drawing = false;
    };
    canvas.addEventListener('pointerup', endStroke);
    canvas.addEventListener('pointercancel', endStroke);
    document.getElementById('signature-clear').addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        signed = false;
        signatureInput.value = '';
        signatureStatus.textContent = 'Tanda tangan belum diisi.';
        refreshSubmit();
    });
    form.addEventListener('submit', (e) => {
        if (
            !matched ||
            !signed ||
            !consent.checked ||
            !layanan.value ||
            !whatsapp.checkValidity()
        ) {
            e.preventDefault();
            return;
        }
        signatureInput.value = canvas.toDataURL('image/png');
        submit.disabled = true;
        submit.textContent = 'Mengirim permohonan...';
    });

    // A failed POST may redirect back with old input; restore corrections, then recheck NIK/NIP.
    if ([16, 18].includes(nik.value.length)) lookupNik(wasRestored);
    refreshSubmit();
})();
