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
