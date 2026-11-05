# TTE-Ticketing v60.1 — Footer gaya Ujian_CAT

**Basis terverifikasi:** ajung5/TTE-Ticketing, branch `main`, commit `05a322ae90c3f186879b28554b64d229c7539c68`.

Paket ini **khusus source v60 yang sudah terpasang**. Perubahan proses bisnis sebelumnya tetap dipertahankan. Jika source belum v60, jangan gunakan patch ini.

Referensi desain: `ajung5/Ujian_CAT`, `resources/views/layouts/guru_baru.blade.php` dan `siswa_baru.blade.php`.

## Perubahan
- Copyright di kiri dan nomor versi di kanan, dipisahkan garis tipis seperti layout Ujian_CAT.
- Satu footer untuk admin, verifikator, dan halaman publik termasuk formulir permohonan, login, dan survei.
- Tahun akhir dinamis menggunakan waktu Asia/Jakarta; versi membaca `config('tte.version')`, yang dapat diatur dengan `TTE_APP_VERSION` dalam `.env`.
- Cache-busting CSS menjadi `tte-footer.css?v=60.1` supaya tampilan baru dapat dimuat.
- Regression test footer diperbarui untuk menguji susunan copyright kiri/versi kanan.

## Instalasi

Salin `patch_tte_footer_ujiancat_v60_1_latest.py` ke root Laravel (sejajar `artisan`).

```bash
python3 patch_tte_footer_ujiancat_v60_1_latest.py --check
python3 patch_tte_footer_ujiancat_v60_1_latest.py
php artisan optimize:clear
```

Perintah pertama tidak mengubah source. Instalasi melakukan backup ke `~/TTE-Ticketing-patch-backups/`, validasi PHP, pengujian Feature v60, dan seluruh test Laravel. Rollback otomatis jika pengujian gagal; installer menghapus dirinya sendiri hanya jika semua tes lulus.

Test dijalankan dengan pembatasan `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` dan lingkungan `testing`, bukan database produksi. Pastikan `vendor/autoload.php` tersedia. Patch tidak mengubah konfigurasi produksi, isi tiket, migrasi, maupun RBAC.

## Tampilan

`Copyright@DiskominfoSubang 2026-TAHUN_BERJALAN`                              `v1.0.0`

`v1.0.0` hanya contoh ketika `TTE_APP_VERSION` belum dikonfigurasi. Atur sesuai kebijakan rilis aplikasi.
