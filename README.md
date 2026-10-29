# TTE-Ticketing

**Sistem permohonan dan penanganan layanan Tanda Tangan Elektronik (TTE)** untuk mendukung pelayanan Sertifikat Elektronik BSrE di lingkungan Pemerintah Kabupaten Subang.

TTE-Ticketing dibangun dengan **Laravel 13** dan menyediakan formulir permohonan publik, antrean kerja verifikator dengan mekanisme *self-claim*, pencatatan hasil pemeriksaan manual pada AMS BSrE, enam jenis pesan WhatsApp, serta survei kepuasan layanan yang terintegrasi.

> **Status proyek (29 Oktober 2026):** tahap pengembangan dan pengujian lokal/staging. Hosting/production **belum dilaksanakan**. README ini disusun berdasarkan `main` commit [`7b085d4`](https://github.com/ajung5/TTE-Ticketing/commit/7b085d4af9a8154188763b8225a369bbb737b0bf). Periksa perubahan pada commit berikutnya sebelum mengikuti petunjuk ini.

**Repository:** <https://github.com/ajung5/TTE-Ticketing>

## Daftar isi

- [Gambaran sistem](#gambaran-sistem)
- [Fitur dan hak akses](#fitur-dan-hak-akses)
- [Proses bisnis](#proses-bisnis)
- [Persyaratan](#persyaratan)
- [Instalasi lokal](#instalasi-lokal)
- [Integrasi Google Sheets](#integrasi-google-sheets)
- [WhatsApp dan survei kepuasan](#whatsapp-dan-survei-kepuasan)
- [Perintah pengembangan dan pengujian](#perintah-pengembangan-dan-pengujian)
- [Konfigurasi lingkungan](#konfigurasi-lingkungan)
- [Catatan keamanan dan kesiapan hosting](#catatan-keamanan-dan-kesiapan-hosting)
- [Struktur kode](#struktur-kode)

## Gambaran sistem

Aplikasi menerima tiga jenis permohonan:

1. **Reset Passphrase** Sertifikat Elektronik;
2. **Pembaruan** Sertifikat Elektronik;
3. **Pendaftaran** TTE baru.

Pemohon tidak memerlukan akun untuk mengajukan permohonan. NIK digunakan untuk mengambil data kepegawaian dari direktori email dinas yang disinkronkan dari Google Sheets. Nama, NIP, jabatan, dan OPD dapat dikoreksi oleh pemohon; **email dinas berasal dari direktori dan tidak dapat diubah** pada formulir. Pemohon mengisi nomor WhatsApp, menyetujui pemrosesan data, dan membubuhkan tanda tangan persetujuan pada kanvas.

**Penting:** tanda tangan pada formulir adalah bukti persetujuan pengajuan, **bukan** TTE tersertifikasi BSrE. Kecocokan NIK di direktori juga **bukan** verifikasi kepemilikan identitas.

## Fitur dan hak akses

| Area | Pemohon (tanpa login) | Verifikator | Administrator |
| --- | --- | --- | --- |
| Form permohonan | Mengirim permohonan | — | — |
| Pencarian NIK | Mengambil data dari direktori email dinas | — | — |
| Antrean tiket | — | Melihat antrean dan mengambil tiket | Statistik agregat |
| Detail tiket | — | Menangani tiket yang diambil | Tidak mengakses detail operasional melalui endpoint verifikator |
| Pemeriksaan AMS BSrE | — | Mencatat status sertifikat dan verifikasi WA secara manual | — |
| Notifikasi WhatsApp | — | Membuka WhatsApp Web dan mengonfirmasi pengiriman secara manual | Mengelola preset pesan |
| Survei kepuasan | Mengisi melalui tautan khusus | — | Melihat rekap dan masukan |
| Profil dan password | — | Mengubah password sendiri | Mengubah password sendiri dan mengirim tautan reset password verifikator |

Fitur utama:

- Mekanisme **self-claim**: satu tiket hanya dapat diambil satu verifikator pada satu waktu.
- Status tiket: `Open` → `In Progress` → `Done`, dengan opsi **Lepas Tiket** sebelum tindakan dicatat.
- Riwayat aktivitas pemeriksaan, catatan, dan konfirmasi pesan.
- NIK terenkripsi di database; NIK lengkap hanya diperlihatkan pada verifikator yang sudah mengambil tiket.
- Tanda tangan disimpan pada *private storage* Laravel.
- Pengelolaan enam preset WhatsApp melalui halaman administrator.
- Survei publik menggunakan **signed URL** dengan masa berlaku 30 hari dan **satu respons per tiket**.
- Halaman responsif untuk pengajuan layanan dan pengisian survei.

## Proses bisnis

```text
Pemohon mengisi formulir
          |
          v
Tiket Open -> Verifikator mengambil tiket (In Progress)
          |
          v
LANGKAH 1: Pemeriksaan manual di AMS BSrE
    - Status sertifikat elektronik: aktif / belum aktif
    - Status nomor WA: terverifikasi / belum terverifikasi
          |
          v
LANGKAH 2: Notifikasi sesuai hasil AMS dan jenis layanan
    - Sertifikat belum aktif -> User belum terdaftar
    - Sertifikat aktif + WA belum terverifikasi -> WA belum terverifikasi
    - Sertifikat aktif + WA terverifikasi -> Reset/Pembaruan/Pendaftaran
          |
          v
WORKFLOW: Catatan opsional; Tandai Selesai / Lepas Tiket*
          |
          v
Tiket Done -> Notifikasi Selesai + tautan survei
          |
          v
Pemohon mengisi survei -> Hasil tersedia di Admin

* Lepas Tiket hanya diizinkan sebelum terdapat tindakan penanganan.
```

### Enam jenis notifikasi WhatsApp

| No. | Jenis notifikasi | Pemicu |
| --- | --- | --- |
| 1 | Nomor WA Belum Terverifikasi | Sertifikat aktif, WA belum terverifikasi pada AMS |
| 2 | Reset Passphrase | Sertifikat aktif, WA terverifikasi, layanan Reset Passphrase |
| 3 | Pembaruan Sertifikat | Sertifikat aktif, WA terverifikasi, layanan Pembaruan |
| 4 | Pendaftaran Diproses | Sertifikat aktif, WA terverifikasi, layanan Pendaftaran |
| 5 | Selesai + Survei | Tiket telah ditandai `Done` |
| 6 | User Belum Terdaftar | Sertifikat belum aktif berdasarkan hasil pemeriksaan AMS |

**Sesuai kode pada commit acuan:** sistem dapat mengizinkan `Done` setelah petugas mengonfirmasi notifikasi kendala (*belum aktif* atau *WA belum terverifikasi*). Kondisi ini perlu dievaluasi sebelum layanan dioperasikan secara publik. Khusus pendaftaran baru, belum memiliki sertifikat aktif belum tentu berarti permohonan tidak valid; tindak lanjut harus mengikuti SOP BSrE yang berlaku.

## Persyaratan

- **PHP 8.3+** (sesuai `composer.json`: `php: ^8.3`), dengan ekstensi yang dibutuhkan Laravel serta `mbstring`, `openssl`, `pdo_mysql` atau `pdo_sqlite`, `curl`, `xml`, dan `gd` untuk pemeriksaan gambar tanda tangan.
- **Composer**.
- **MySQL** untuk lingkungan pengembangan yang menyerupai produksi; SQLite juga dapat digunakan untuk pengujian lokal.
- **Node.js/npm** hanya jika diperlukan untuk mengubah atau membangun aset frontend. Tampilan yang digunakan saat ini memuat CSS/JS langsung dari `public/`.
- Akun service account Google dengan akses *read-only* ke spreadsheet jika menggunakan sinkronisasi direktori online.

> Proyek ini dapat dijalankan secara lokal menggunakan `php artisan serve`; tidak memerlukan Docker.

## Instalasi lokal

### 1. Clone dan pasang dependency

```bash
 git clone https://github.com/ajung5/TTE-Ticketing.git
 cd TTE-Ticketing
 composer install
 cp .env.example .env
 php artisan key:generate
```

### 2. Pilih database

**Opsi A — MySQL:** buat database, misalnya `tte_ticketing`, kemudian atur `.env`:

```dotenv
APP_NAME="TTE Ticketing"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tte_ticketing
DB_USERNAME=USERNAME_MYSQL
DB_PASSWORD=PASSWORD_MYSQL
```

**Opsi B — SQLite:** buat file `database/database.sqlite` jika belum ada, lalu ubah `.env` menjadi:

```dotenv
DB_CONNECTION=sqlite
```

### 3. Jalankan migrasi dan server

```bash
php artisan migrate
php artisan optimize:clear
php artisan serve
```

Buka <http://127.0.0.1:8000/permohonan>.

Jika ingin **akun dan tiket contoh hanya untuk pengembangan lokal**, gunakan `php artisan db:seed`. Seeder berisi data dummy dan akun demo, sehingga **jangan** dijalankan untuk melayani data produksi.

### 4. Siapkan pengguna admin dan verifikator

Aplikasi membedakan `role = admin` dan `role = verifier`. Pada instalasi baru, akun produksi perlu diprovisikan secara terkontrol; repository belum memiliki wizard pembuatan admin produksi. Gunakan proses provisioning internal yang aman, bukan akun demo. Jangan menyimpan kredensial akun nyata di kode atau README.

## Integrasi Google Sheets

Sumber direktori adalah spreadsheet yang mempunyai header minimal **`NIK`** dan **`Email Gov`**. Kolom tambahan yang dapat digunakan antara lain `NAMA`, `NIP`, `JABATAN`, `NAMA UNOR`, dan `UNIT KERJA`.

Tambahkan konfigurasi pada `.env` (nilai berikut hanya **placeholder**, bukan kredensial):

```dotenv
TTE_EMAIL_SHEET_ID="ID_SPREADSHEET_ANDA"
TTE_EMAIL_SHEET_RANGE="Data ASN Merge!A1:R"
TTE_GOOGLE_SERVICE_ACCOUNT_JSON="/path/aman/google_credentials.json"
TTE_EMAIL_DIRECTORY_MAX_AGE_HOURS=168
TTE_EMAIL_ADMIN_CONTACT_WA="628XXXXXXXXXX"
```

1. Bagikan spreadsheet kepada alamat email service account sebagai **Viewer**.
2. Simpan berkas JSON service account di lokasi privat, **di luar** direktori `public/`; jangan pernah commit ke GitHub.
3. Jalankan sinkronisasi:

```bash
php artisan tte:sync-email-directory
```

Untuk uji impor dari CSV lokal:

```bash
php artisan tte:sync-email-directory --csv=/path/ke/direktori.csv
```

Sinkronisasi bersifat atomik: jika input tidak valid, data direktori lama tidak langsung dihapus. Secara default aplikasi menganggap direktori kedaluwarsa setelah **168 jam** tanpa sinkronisasi berhasil. Jika spreadsheet dan kredensial telah dikonfigurasi, Laravel Scheduler menjadwalkan sinkronisasi **setiap jam**.

> Scheduler memerlukan `php artisan schedule:run` dipanggil setiap menit oleh cron pada lingkungan server. Tugas penyiapan cron dilakukan saat deployment hosting nantinya.

## WhatsApp dan survei kepuasan

### WhatsApp

- Pengiriman dilakukan **manual melalui WhatsApp Web**, bukan WhatsApp Business API.
- Petugas memilih **Buka WhatsApp Web**, mengirim pesan, kemudian menekan **Saya Sudah Mengirim Pesan**.
- Konfirmasi menandakan **petugas mencatat tindakan pengiriman**, bukan bukti pesan sudah diterima atau dibaca.
- Redaksi enam notifikasi dikelola admin melalui `/admin/wa-templates`.
- Nomor WhatsApp pengelola email diatur melalui `TTE_EMAIL_ADMIN_CONTACT_WA` agar pemohon dapat menghubungi helpdesk ketika NIK/email dinas belum tersedia.

### Survei

- Notifikasi Selesai mencantumkan URL survei milik TTE-Ticketing sendiri.
- URL survei ditandatangani Laravel dan berlaku selama **30 hari**; perubahan `APP_KEY` dapat membatalkan tanda tangan tersebut.
- Setiap tiket hanya dapat menyimpan **satu tanggapan survei**.
- Form mencatat **nama pengisi survei**, empat penilaian skala 1–5, dan saran opsional.
- Hasil dilihat administrator di `/admin/survei` dengan kolom **Waktu, Nomor Tiket, Nama Pengisi Survei, Nilai, Saran/Masukan**.
- Nama pengisi survei merupakan **isian responden**, bukan identitas yang telah diverifikasi secara terpisah.

## Rute utama

| Path | Fungsi | Akses |
| --- | --- | --- |
| `/permohonan` | Formulir pengajuan | Publik |
| `/permohonan/berhasil` | Konfirmasi pengajuan | Publik (memerlukan session pengajuan) |
| `/login` | Login petugas | Publik |
| `/tickets` | Antrean tiket | Verifikator |
| `/tickets/{ticket}` | Detail dan penanganan tiket | Verifikator berwenang |
| `/admin` | Statistik layanan | Admin |
| `/admin/wa-templates` | Pengaturan preset WhatsApp | Admin |
| `/admin/survei` | Rekap dan masukan survei | Admin |
| `/survei/{ticket}` | Form survei melalui tautan bertanda tangan | Pemegang signed URL |
| `/up` | Health check Laravel | Sesuai konfigurasi server |

## Perintah pengembangan dan pengujian

```bash
# Semua pengujian unit dan feature
php artisan test

# Pengujian penting yang terkait layanan dan direktori
php artisan test tests/Feature/PublicEmailDirectoryTest.php
php artisan test tests/Feature/PublicRequestAndAmsVerificationTest.php
php artisan test tests/Feature/PublicSurveyTest.php
php artisan test tests/Feature/TteManagementTest.php

# Periksa route, scheduler, dan kondisi aplikasi
php artisan route:list
php artisan schedule:list
php artisan about

# Bersihkan cache lokal setelah mengubah Blade / konfigurasi
php artisan optimize:clear

# Audit library PHP
composer audit
```

Pengujian menggunakan Pest/Laravel dan konfigurasi SQLite in-memory dalam `phpunit.xml`; diperlukan ekstensi `pdo_sqlite`. Keberadaan test tidak berarti seluruh test telah dijalankan atau lulus pada setiap lingkungan.

## Konfigurasi lingkungan

| Variabel | Keterangan |
| --- | --- |
| `APP_KEY` | Kunci enkripsi Laravel; **jangan berubah** setelah menyimpan data sungguhan |
| `APP_ENV` / `APP_DEBUG` | Gunakan `production` dan `false` saat hosting |
| `APP_URL` | URL aplikasi (gunakan HTTPS pada production; dipakai juga dalam tautan survei) |
| `DB_*` | Database dan kredensial database |
| `TTE_EMAIL_SHEET_ID` | ID Google Spreadsheet direktori |
| `TTE_EMAIL_SHEET_RANGE` | Rentang sheet data pegawai |
| `TTE_GOOGLE_SERVICE_ACCOUNT_JSON` | Path absolut ke JSON kredensial service account |
| `TTE_EMAIL_DIRECTORY_MAX_AGE_HOURS` | Batas umur direktori agar dianggap tersedia |
| `TTE_EMAIL_ADMIN_CONTACT_WA` | Nomor pengelola email, format `628...` |
| `TTE_SERVICE_NAME` | Nama layanan yang tampil pada pesan |
| `ZOHO_WEBHOOK_SECRET` | Rahasia webhook Zoho jika integrasi lama digunakan; harus diisi kuat dan disamakan di kedua pihak |
| `MAIL_*` | SMTP untuk email reset password petugas |
| `SESSION_*` | Konfigurasi sesi dan secure cookie |

**Jangan commit** `.env`, kredensial service account, password, file tanda tangan, atau data pemohon ke repository.

## Catatan keamanan dan kesiapan hosting

**Hosting ditunda pada tahap dokumentasi ini.** Sebelum mengizinkan akses publik dengan data pribadi sebenarnya, lakukan minimal pemeriksaan berikut:

- [ ] Terapkan verifikasi kepemilikan identitas untuk pencarian NIK atau batasi akses ke jaringan internal/VPN. **Saat ini endpoint publik dapat mengembalikan nama, NIP, jabatan, OPD, dan email lengkap hanya berdasarkan NIK**, dengan rate limit tetapi tanpa pembuktian pemilik NIK.
- [ ] Tinjau ulang aturan status `Done` bagi tiket dengan sertifikat belum aktif atau WA belum terverifikasi, khususnya pada **Pendaftaran Baru**.
- [ ] Pastikan `APP_ENV=production`, `APP_DEBUG=false`, HTTPS aktif, dan document root web server menunjuk ke `public/`.
- [ ] Siapkan akun admin/verifikator nyata dengan password kuat dan email pemulihan yang dapat digunakan.
- [ ] Konfigurasikan SMTP, koneksi Google Sheets, sinkronisasi terjadwal, domain, dan sertifikat HTTPS.
- [ ] Jalankan `php artisan test`, `composer audit`, pemeriksaan migrasi, dan pengujian end-to-end di staging.
- [ ] Cadangkan database, file `storage/app/private`, dan `APP_KEY` secara aman; uji proses pemulihan.
- [ ] Tetapkan pengendalian akses, audit log, kebijakan retensi data, serta penanganan insiden sesuai kebijakan instansi.

Jangan menjalankan `php artisan serve` untuk server production. Panduan deployment lengkap akan ditentukan kemudian berdasarkan jenis hosting yang dipilih (VPS/VM, provider, atau shared hosting).

## Struktur kode

```text
app/
  Console/Commands/SyncEmailDirectory.php
  Http/Controllers/
    PublicTicketController.php
    TicketController.php
    PublicSurveyController.php
    AdminSurveyController.php
    WaTemplateController.php
  Models/
  Services/
    EmailDirectoryService.php
    EmailDirectorySync.php
    GoogleSheetDirectoryReader.php
    WhatsAppMessage.php
config/
  email_directory.php
  tte.php
database/
  migrations/
  seeders/
public/
  css/
  js/
resources/views/
  public/
  tickets/
  admin/
routes/
  web.php
  api.php
  console.php
tests/Feature/
```

## Catatan dokumentasi

README ini menjelaskan **fitur yang ada di kode**, bukan pernyataan bahwa layanan telah menerima persetujuan operasional atau telah di-deploy. Seluruh contoh domain, nomor telepon, path, dan kredensial bersifat ilustratif. Perubahan proses bisnis atau integrasi BSrE selanjutnya harus diikuti pembaruan dokumentasi dan pengujian.
