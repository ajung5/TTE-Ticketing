# Patch TTE-Ticketing v4 — Pencocokan NIK, Direktori Google Sheets, dan Signature Pad

Basis: `ajung5/TTE-Ticketing` `main` commit `5b6897982ef24c2dd8a5af934a6196d1cb8eca27` (29 Oktober 2026). Patch ini **hanya** memuat file baru atau file yang berubah dari commit tersebut.

## Pemasangan

1. **Backup database, berkas `.env`, serta storage privat.** Merge isi ZIP ke root Laravel. Jangan mengganti folder Laravel seluruhnya. Jangan menjalankan `migrate:fresh`.
2. Jalankan:

   ```bash
   php artisan optimize:clear
   php artisan migrate
   php artisan route:list --path=permohonan
   php artisan test tests/Feature/PublicEmailDirectoryTest.php tests/Feature/PublicRequestAndAmsVerificationTest.php
   php artisan serve
   ```
3. Buka `/permohonan`. Sampai direktori disinkronkan, endpoint pengecekan NIK mengembalikan *layanan belum tersedia* (bukan *NIK tidak ditemukan*).
4. Di `.env` yang sudah ada, **tambahkan** variabel `TTE_EMAIL_*` dan `TTE_GOOGLE_SERVICE_ACCOUNT_JSON` sesuai contoh `.env.example`. **Jangan menimpa `.env` produksi.**

## Sinkronisasi dari Google Spreadsheet privat

1. Di Google Cloud Project, aktifkan **Google Sheets API** dan buat **service account** dengan key JSON untuk penggunaan server-to-server (penyimpanan kunci harus mematuhi kebijakan keamanan organisasi; utamakan workload identity jika tersedia).
2. Bagikan spreadsheet ke alamat email service account sebagai **Viewer**; jangan jadikan spreadsheet publik.
3. Simpan JSON di luar web root dan repository, misalnya `/etc/tte/keys/google-sheets.json` (Linux) atau `C:\\secrets\\tte-google-sheets.json` (Windows), dengan izin filesystem minimum.
4. Salin Spreadsheet ID dari URL Google Sheets (antara `/d/` dan `/edit`), dan isikan nama tab/range yang benar dalam `.env`:

   ```dotenv
   TTE_EMAIL_SHEET_ID="ISI_ID_SPREADSHEET_ASLI"
   TTE_EMAIL_SHEET_RANGE="Data!A1:R"
   TTE_GOOGLE_SERVICE_ACCOUNT_JSON="/path/to/google-sheets.json"
   TTE_EMAIL_DIRECTORY_MAX_AGE_HOURS=168
   TTE_EMAIL_ADMIN_CONTACT_URL="https://URL-RESMI-PENGELOLA-EMAIL"
   ```

5. Jalankan `php artisan config:clear`, lalu `php artisan tte:sync-email-directory`.
6. Untuk sinkronisasi berkala: `routes/console.php` menjadwalkan sinkronisasi per jam jika ID Spreadsheet dan file credential telah diatur. Pastikan scheduler Laravel benar-benar berjalan (`php artisan schedule:run` setiap menit via cron/Windows Task Scheduler). Gunakan akun OS dengan akses baca JSON key, akses database, dan TLS yang benar.

**Penting:** Field NIK spreadsheet harus bertipe **Plain text** (16 digit) dan tidak berubah menjadi notasi ilmiah. Data baris pertama harus berisi header; minimal `NIK` dan `Email Gov`. Kolom 18 yang disebutkan pengguna didukung, termasuk `NAMA`, `NIP`, `JABATAN`, `NAMA UNOR`, `UNIT KERJA`, `Status Pengguna`, dan `Status Sertifikat`. Kolom lain tidak disalin demi minimisasi data.

## Pengujian tanpa kredensial Google Sheets

Ekspor spreadsheet ke CSV UTF-8, atau gunakan data fiktif dari `docs/CONTOH-DIREKTORI-EMAIL-TEST.csv`:

```bash
php artisan tte:sync-email-directory --csv="/path/ke/file.csv"
```

Perintah **mengganti isi** direktori lokal dengan data dari CSV/Sheet yang berhasil divalidasi, secara atomik. Jangan gunakan contoh CSV untuk produksi. Sinkronisasi yang gagal tidak menghapus direktori lama. Batas usia data default 168 jam; jika lewat, pengecekan publik akan sementara ditahan sampai sinkronisasi baru berhasil.

## Alur form publik

- Masukkan NIK 16 digit -> `POST /permohonan/cek-nik` dengan CSRF, rate limit per-IP 6/menit dan 120/hari -> hasil `found` + email tersamarkan, `not_found` (NIK tak tersedia **atau** Email Gov kosong), atau `503` (direktori belum siap/kedaluwarsa).
- Modal **Hubungi Pengelola Email** hanya untuk kondisi `not_found`. Nomor/URL kontak harus dikonfigurasi pada `TTE_EMAIL_ADMIN_CONTACT_URL`; tanpa kontak, pengguna diberi arahan helpdesk internal. Pencarian tidak membuka seluruh email ataupun identitas pegawai.
- NIK/email tidak diambil dari input email browser saat submit; server mengecek NIK lagi dan mengisi Email Gov sebenarnya dari direktori, mencegah pemalsuan field email.
- Form wajib: layanan, nama, NIK, nomor WA, unit kerja, jabatan, checkbox persetujuan, tanda tangan canvas. NIP opsional. Signature berupa PNG di `Storage::disk('local')` (`storage/app/private/signatures/...` secara default Laravel baru); tidak punya URL publik. Tiket menyimpan `signature_path` dan `consent_accepted_at`. Verifikator yang sudah mengklaim tiket dapat melihatnya melalui route privat `/tickets/{ticket}/signature`; admin dan verifikator lain tidak memiliki akses.
- Ini tanda tangan persetujuan layar, **bukan** tanda tangan elektronik tersertifikasi dan **bukan** bukti identitas terverifikasi. Petugas tetap wajib memverifikasi identitas dan nomor WA di AMS BSrE.
- Kecocokan NIK juga bukan bukti pemohon merupakan pemilik NIK tersebut. Hindari mengandalkan kecocokan direktori saja untuk aksi berisiko seperti reset passphrase BSrE.
- Tidak mengubah alur lama `self-claim`, pemeriksaan WA, 9 preset pesan, webhook Zoho, 100 dummy, atau hak akses admin.

## Kebijakan keamanan sebelum production

- Lindungi `.env`, JSON service account, `APP_KEY` dan folder signature; jangan pernah menyimpan file key di Git atau `public`.
- **Jangan mengganti APP_KEY** pada aplikasi yang sudah menyimpan NIK terenkripsi; `nik_hash` dan field terenkripsi bergantung pada key yang stabil. Jika perlu rotasi, rancang migrasi kunci terlebih dahulu.
- HTTPS wajib; debug harus `false`; gunakan WAF/CAPTCHA/Turnstile dan monitoring aktivitas pencarian agar endpoint NIK publik tidak dipakai untuk enumerasi identitas. Rate limit per-IP saja tidak cukup untuk sistem pemerintahan.
- Rekam persetujuan dan tetapkan kebijakan retensi, akses terbatas, backup terenkripsi, serta prosedur penghapusan tanda tangan dan data permohonan. Jangan log isi NIK/email lengkap dari payload.
- Validasi directory sync gagal jika NIK invalid/duplikat atau Email Gov berformat tidak sah. Hubungi pengelola sumber data untuk perbaikan.
