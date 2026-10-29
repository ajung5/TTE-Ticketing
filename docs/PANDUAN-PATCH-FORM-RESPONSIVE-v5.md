# TTE-Ticketing — Form Responsif dan Autofill dari NIK (v5)

Basis: repo `ajung5/TTE-Ticketing`, branch `main`, commit `3db81e7dc6395b27a3ce9e7ad00a2ea1a417a98c` (29 Oktober 2026).
Paket ini hanya memuat file baru/berubah; jangan mengganti folder proyek secara keseluruhan.

## Perilaku

1. Pemohon membuka `/permohonan`, mengisi NIK 16 digit dan memilih jenis layanan.
2. Setelah NIK lengkap, browser mengirim POST ke `/permohonan/cek-nik` dengan CSRF token; Laravel mencari di direktori hasil sinkronisasi spreadsheet.
3. Jika cocok dan Email Gov ada, nama, NIP, jabatan, unit kerja/OPD, dan email lengkap terisi otomatis. Nama, NIP, jabatan, dan OPD dapat diperbaiki pemohon. Email readonly, tidak disertakan sebagai input bernama, dan pada `store()` selalu diambil ulang dari direktori.
4. Nomor WhatsApp tidak tersedia di 18 kolom spreadsheet; pemohon tetap harus mengisinya manual.
5. Form tetap mengharuskan checkbox persetujuan dan tanda tangan. Jika NIK tidak ada atau Email Gov kosong, tampilkan modal kontak pengelola.

## Instalasi

Gabungkan semua file dari ZIP ke root repo; backup perubahan lokal dahulu. Kemudian:

```bash
php artisan optimize:clear
php artisan test tests/Feature/PublicEmailDirectoryTest.php
php artisan test
php artisan serve
```

Tidak ada migrasi database baru pada patch ini. Sinkronisasi direktori tetap melalui:

```bash
php artisan tte:sync-email-directory
```

## Konfigurasi kontak pengelola

Gunakan **nomor WA saja** pada `.env`:

```dotenv
TTE_EMAIL_ADMIN_CONTACT_WA="6281234567890"
```

Ganti dengan nomor resmi (nomor di atas hanya contoh). Nomor `08...` atau `628...` dapat dikenali; modal membentuk `https://wa.me/62...`. Variabel lama `TTE_EMAIL_ADMIN_CONTACT_URL` tidak dipakai lagi.

## Keamanan dan batasan

**PENTING:** Endpoint lookup publik sekarang mengembalikan nama, NIP, jabatan, OPD, dan **email lengkap** hanya dengan NIK. Ini mempunyai risiko pengungkapan data pegawai melalui NIK yang diketahui atau pencarian massal. Pembatasan 6 permintaan/menit dan 120/hari per IP sudah ada di `AppServiceProvider`; ini belum memadai sebagai kontrol tunggal untuk production. Sebelum online dengan data ASN riil, tetapkan dasar pemrosesan dan mekanisme pembuktian pemohon, serta tambah anti-bot/CAPTCHA, pemantauan abuse, dan proteksi data. Hindari ekspos data sensitif lain.

Kecocokan NIK tidak membuktikan identitas orang yang mengisi form. Verifikator tetap wajib memeriksa identitas sesuai SOP dan status WhatsApp melalui AMS BSrE. Tanda tangan canvas bukan tanda tangan tersertifikasi BSrE.

Di tampilan mobile (360–430 px) data tersusun satu kolom, label tidak terpotong, dan signature pad dapat digunakan dengan sentuhan.

Pemeriksaan lokal pembuat patch: sintaks PHP/JavaScript dan integritas arsip. Test Laravel penuh perlu dijalankan di proyek yang memiliki `vendor/`.
