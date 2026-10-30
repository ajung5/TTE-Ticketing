# Laporan Tiket TTE-Ticketing

**Versi fitur:** v10 — rekap periode bulanan dan periode tanggal custom. **Akses:** administrator (`role=admin`) saja. **Basis kode:** `main` commit `ebf7765` (29 Oktober 2026 UTC). Dokumen ini menjelaskan fitur yang ditambahkan melalui patch; **belum di-push ke GitHub**.

## Mengakses

Login admin, kemudian pilih menu **Laporan Tiket** di sidebar atau buka `/admin/laporan-tiket`.

## Penggunaan

1. Pilih **Per Bulan** dan bulan yang diinginkan (misalnya `2026-10`) **atau** pilih **Tanggal Custom** dan isi tanggal awal/akhir.
2. Tekan **Tampilkan Laporan**.
3. Periksa lima indikator utama, distribusi per jenis layanan, kinerja verifikator, sumber permohonan, serta tabel rincian tiket.
4. Pilih **Unduh CSV Lengkap** untuk semua tiket masuk pada periode tersebut (bukan hanya halaman pagination); berkas berekstensi `.csv` menggunakan UTF-8 BOM dan pemisah titik koma `;` agar nyaman dibuka di aplikasi spreadsheet.
5. Pilih **Cetak Ringkasan / Simpan PDF** untuk menggunakan dialog cetak browser. Versi cetak **hanya berisi rekap**, bukan seluruh rincian tiket. Cetak ke PDF melalui pilihan browser jika diperlukan.

## Definisi indikator

- **Tiket Masuk:** semua tiket dengan `created_at` di rentang tanggal WIB yang dipilih.
- **Belum Diambil / Dalam Proses / Selesai (Tiket Masuk):** status **saat laporan dibuat** untuk tiket yang masuk dalam rentang itu. Angka historis tidak dibekukan pada akhir bulan.
- **Selesai Selama Periode:** semua tiket dengan `completed_at` dalam rentang, **termasuk yang masuk sebelum rentang laporan**. Angka ini tidak harus sama dengan `Selesai (Tiket Masuk)`.
- **Rekap verifikator:** penanggung jawab (`claimed_by`) saat laporan dibuat, dihitung hanya dari tiket yang **masuk** dalam rentang; petugas yang soft-deleted tetap dapat tampil untuk histori.
- **Sumber:** `direct`, `zoho`, `simulated`, `demo`, atau nilai lain yang ada dalam database. Dummy/simulasi **tidak otomatis dikecualikan** dan harus dipilah sebelum dijadikan laporan kedinasan resmi.

**Batas tanggal:** diproses inklusif menurut kalender `Asia/Jakarta`, sedangkan filter SQL menggunakan batas UTC `[start, end+1 hari)`. Tanggal 31 Oktober pukul 23.59 WIB masih masuk laporan Oktober.

## Keamanan dan privasi

- Route tampilan dan CSV memeriksa peran admin di controller, bukan hanya menyembunyikan menu.
- CSV tidak memuat NIK, nomor WhatsApp, atau email pemohon. Kolom yang dikeluarkan meliputi nomor tiket, waktu masuk, nama pemohon, unit kerja, layanan, sumber, status, petugas, dan waktu selesai.
- Penanganan CSV formula injection dilakukan sebelum mengirim sel ke spreadsheet.
- Respons berisi data admin disertai `Cache-Control: private, no-store`.
- Simpan file CSV/PDF di lokasi terotorisasi; nama pemohon masih termasuk data pribadi. Tidak disediakan URL publik untuk laporan.

## Instalasi

Salin seluruh isi ZIP patch ke root aplikasi sesuai relative path. Tidak ada migrasi baru dan tidak diperlukan paket Composer tambahan.

```bash
php artisan optimize:clear
php artisan route:list --name=admin.reports
php artisan test tests/Feature/AdminTicketReportTest.php
php artisan test
php artisan serve
```

Buka `/admin/laporan-tiket`. Verifikasi bahwa login verifikator ditolak dan filter, CSV serta cetak berfungsi.

## File yang ditambah atau diubah

```text
app/Http/Controllers/AdminTicketReportController.php  (baru)
resources/views/admin/ticket-reports.blade.php         (baru)
resources/views/layouts/app.blade.php                   (ubah)
routes/web.php                                          (ubah)
tests/Feature/AdminTicketReportTest.php                (baru)
docs/LAPORAN-TIKET.md                                   (baru)
```

> Catatan: jika repository GitHub telah berubah lagi setelah commit acuan, pastikan dua file yang dimodifikasi (`routes/web.php` dan `layouts/app.blade.php`) direkonsiliasi dahulu agar perubahan terbaru tidak tertimpa.
