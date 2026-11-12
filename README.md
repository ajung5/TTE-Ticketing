# TTE-Ticketing

**Sistem Manajemen Layanan Tanda Tangan Elektronik (TTE) dan Sertifikat Elektronik (SE)** untuk mendukung pelayanan di lingkungan Pemerintah Kabupaten Subang.

Aplikasi **Laravel 13** ini menyediakan formulir permohonan tanpa login, direktori email dinas dari Google Sheets, antrean **self-claim**, pemeriksaan manual AMS BSrE, komunikasi WhatsApp Web (desktop) / aplikasi WhatsApp native (mobile), email pemberitahuan tiket baru ke satu inbox operasional, laporan, dan survei kepuasan layanan. Tampilan admin/verifikator menggunakan panel **Tindakan Berikutnya**, dashboard kesehatan sistem *read-only*, serta footer copyright dan versi aplikasi yang konsisten.

> **Status dokumentasi:** panduan dasar diverifikasi terhadap kode branch `main` per **5 November 2026**, baseline [`26504c2`](https://github.com/ajung5/TTE-Ticketing/commit/26504c27176a53f3f26f00eece0f62f4ae7a26d8). Ringkasan perubahan di bawah diverifikasi terhadap source sampai commit [`1e842a5`](https://github.com/ajung5/TTE-Ticketing/commit/1e842a57234032c04125dda4aac387e44d42b5c2) (**10 November 2026**). Pembaruan dokumentasi **tidak membuktikan** kesiapan konfigurasi server, SMTP, sinkronisasi direktori, keamanan, atau keberhasilan seluruh test pada deployment tertentu.
>
> **Perhatian production:** pencarian NIK/NIP pada versi ini **tidak menggunakan OTP/verifikasi email** dan masih dapat menampilkan profil pegawai berdasarkan NIK/NIP yang cocok. Rate limit telah tersedia, tetapi **bukan pembuktian kepemilikan identitas**. Sebelum akses dibuka luas, pastikan pengelola layanan menerima risikonya dan menerapkan pembatasan jaringan/WAF/anti-enumerasi yang memadai.

## Versi rilis, perubahan fitur, dan perbaikan bug

Versi aplikasi yang tampil di footer bersumber dari **file `VERSION`** (baseline: `1.0.0`). Perubahan fitur dan bug dicatat terpisah dengan ID `FEAT-...` dan `FIX-...` di [`CHANGELOG.md`](CHANGELOG.md). Label paket implementasi seperti `v6.20.1` bukan versi rilis aplikasi. `VERSION=1.0.0` tidak membuktikan suatu rilis sudah di-deploy; status rilis memerlukan tag Git, tes dan catatan deployment. Lihat [`docs/VERSIONING.md`](docs/VERSIONING.md) untuk tata cara merilis.

## Pembaruan implementasi — 10 November 2026

Bagian ini hanya mencatat perubahan sejak baseline dokumentasi 5 November; bagian panduan lain tetap dipertahankan.

- **v6.13 — UX layanan:** filter antrean verifikator **Perlu Tindakan** dan **Menunggu Pemohon** (tetap menghormati kepemilikan tiket), ringkasan hasil AMS tersimpan pada detail tiket, bantuan jika NIK/NIP tidak ditemukan pada formulir publik, dan tombol salin nomor tiket setelah pengajuan berhasil.
- **v6.13–v6.14 — Dashboard admin:** ringkasan **Bulan Berjalan**, **7 Hari Terakhir**, **Semua Waktu**, dan **Tanggal Kustom** dengan tanggal awal/akhir inklusif menurut WIB. *Tiket Masuk* berdasarkan `created_at`, *Selesai dalam Periode* berdasarkan `completed_at`; indikator antrean saat ini dan statistik akumulasi tetap tidak dipengaruhi filter periode.
- **v6.15 — Preset WhatsApp:** tanda tangan pesan dapat memakai **`{nama_verifikator}` atau `{inisial_verifikator}`**. Inisial akun dapat ditulis bebas (maksimum 12 karakter, tanpa karakter kontrol/baris baru), tidak dipaksa huruf kapital, dan kembali ke nama lengkap jika inisial kosong. Preset yang telah disimpan tidak ditimpa.
- **v6.16 — Profil akun:** administrator dan verifikator dapat memperbarui **nama, email login, dan inisial sendiri** melalui `/profile`. Perubahan email mensyaratkan **password saat ini**; perubahan password tetap di form terpisah. Field **Peran** bersifat `readonly` dan telah diberi tampilan read-only seperti field email pada formulir permohonan. Peran dan hak akses tetap ditentukan di backend, bukan oleh input profil.

- **v6.17 — Konsolidasi aset frontend:** 11 CSS dan 10 JS di `public/` menjadi **4 CSS dan 3 JS** (`tte-app`, `tte-request`, `tte-ticket`, `tte-survey`); loader Blade serta regression test diadaptasi. Urutan deklarasi CSS dan logika JS dipertahankan, tanpa perubahan alur bisnis, otorisasi, atau skema database.
- **v6.18.1 — Cache aset otomatis dan penataan test:** URL CSS/JS/icon memakai hash SHA-256 isi file melalui `App\Support\VersionedAsset`; tidak ada nomor `?v=` manual pada Blade. Test Pest dikelompokkan menurut fungsi di `tests/Feature/{Accounts,Public,Tickets,Notifications,Reporting,Surveys,Infrastructure,Presentation}` dan seluruh test dari `tests/Feature/Current` dipindahkan; test tanpa `RefreshDatabase` tetap berdiri sendiri demi menjaga lifecycle. Tidak ada migrasi database atau perubahan alur layanan.
Perubahan profil dan inisial **tidak memerlukan migrasi database**. Uji pada lingkungan development/staging dengan `php artisan test`, termasuk `tests/Feature/Accounts/AccountManagementTest.php`, `tests/Feature/Notifications/WhatsAppTemplatesTest.php`, dan `tests/Feature/Reporting/DashboardAndTicketReportsTest.php`.

---

## Daftar isi

1. [Fitur dan peran pengguna](#1-fitur-dan-peran-pengguna)
2. [Proses bisnis terkini](#2-proses-bisnis-terkini)
3. [Notifikasi WhatsApp dan hasil pelayanan](#3-notifikasi-whatsapp-dan-hasil-pelayanan)
4. [Arsitektur dan persyaratan](#4-arsitektur-dan-persyaratan)
5. [Instalasi lokal](#5-instalasi-lokal)
6. [Instalasi production baru (VPS dan aaPanel)](#6-instalasi-production-baru-vps-dan-aapanel)
7. [Pembaruan deployment pada database existing](#7-pembaruan-deployment-pada-database-existing)
8. [Konfigurasi Google Sheets, email, WhatsApp, dan scheduler](#8-konfigurasi-google-sheets-email-whatsapp-dan-scheduler)
9. [Pengujian dan pemeriksaan penerimaan](#9-pengujian-dan-pemeriksaan-penerimaan)
10. [Operasional, keamanan, dan backup](#10-operasional-keamanan-dan-backup)
11. [Troubleshooting](#11-troubleshooting)
12. [Rute dan struktur proyek](#12-rute-dan-struktur-proyek)
13. [Dokumentasi operasional yang dipertahankan](#13-dokumentasi-operasional-yang-dipertahankan)

---

## 1. Fitur dan peran pengguna

### Layanan yang tersedia

| Kode di database | Nama yang ditampilkan |
| --- | --- |
| `Pendaftaran` | Penerbitan Sertifikat Elektronik / pendaftaran TTE |
| `Reset Passphrase` | Reset Passphrase |
| `Pembaruan` | Pembaruan Sertifikat Elektronik |

### Hak akses

| Kapabilitas | Pemohon (tanpa login) | Verifikator | Administrator |
| --- | :---: | :---: | :---: |
| Mengisi formulir permohonan | Ya | — | — |
| Mencari NIK 16 digit atau NIP 18 digit melalui direktori | Ya | — | — |
| Mengambil tiket baru (*self-claim*) | — | Ya | **Tidak** |
| Memeriksa AMS, mengirim pesan layanan, menyelesaikan atau mengembalikan tiket | — | Tiket miliknya | **Tidak** |
| Melihat statistik tiket | — | Statistik pekerjaan sendiri | Statistik keseluruhan |
| Mengelola akun verifikator | — | — | Ya |
| Mengelola template WhatsApp | — | — | Ya |
| Melihat laporan tiket dan mengunduh CSV | — | — | Ya |
| Mengisi survei melalui signed URL | Ya | — | — |
| Melihat hasil survei | — | — | Ya |
| Memperbarui profil sendiri (nama, email, inisial) | — | Ya | Ya |
| Mengubah password sendiri | — | Ya | Ya |
| Mengirim tautan reset password verifikator | — | — | Ya |

**Aturan penting:** administrator tidak menugaskan, mengambil alih, memeriksa AMS, atau menyelesaikan tiket atas nama verifikator. Verifikator hanya boleh memproses tiket yang sudah diambil sendiri. Admin dapat menambah, memperbarui, menonaktifkan, dan memulihkan akun verifikator menurut aturan aplikasi; penghapusan akun menggunakan *soft delete* untuk mempertahankan riwayat.

**UX terkini:** antrean verifikator memprioritaskan **Milik Saya**, sedangkan antrean Baru diurutkan dari tiket yang lebih lama; halaman detail menonjolkan layanan diajukan dan panel **Tindakan Berikutnya** berbasis keputusan backend. Admin memiliki statistik, rincian laporan dan indikator kesehatan direktori/SMTP/queue yang **informatif saja** (bukan bukti pengiriman email atau keberhasilan scheduler). Preset WA memiliki pratinjau dengan data fiktif dan panduan token di setiap editor. Footer seluruh halaman menampilkan copyright **DiskominfoSubang 2026–tahun berjalan** di kiri serta `vX.X.X` di kanan sesuai file `VERSION` di root repository.

### Formulir permohonan

Pemohon mengisi NIK/NIP, jenis layanan, nomor WhatsApp, data profil, persetujuan pemrosesan data, dan tanda tangan pada kanvas. Nama, NIP, jabatan, serta OPD dapat dikoreksi oleh pemohon; **alamat email dinas diambil ulang dari direktori pada sisi server** dan tidak boleh diubah lewat browser. UPTD berasal dari direktori.

Tanda tangan pada formulir adalah **bukti persetujuan pengajuan**, bukan TTE tersertifikasi BSrE. Gambar tanda tangan disimpan di penyimpanan privat Laravel, tidak sebagai file publik.

## 2. Proses bisnis terkini

### Status tiket

| Status teknis | Label tampilan | Makna |
| --- | --- | --- |
| `Open` | Baru | Tiket masuk dan belum diambil |
| `In Progress` | Diproses | Sudah diambil verifikator |
| `Done` | Selesai | Penanganan tiket telah ditutup sesuai aturan layanan |
| `Returned` | Perlu Pengajuan Ulang | Jenis permohonan perlu dikoreksi dan pengajuan baru diperlukan |

Tiket mempunyai nomor unik. Tombol **Lepas Tiket** hanya dapat digunakan sebelum ada tindakan penanganan selain aktivitas ambil/lepas. Catatan tindak lanjut bersifat opsional.

### Alur umum

```text
Pemohon mengisi formulir publik
          │
          ▼
Tiket Open → verifikator melakukan self-claim
          │
          ▼
Tiket In Progress
          │
          ▼
LANGKAH 1 — Pemeriksaan AMS BSrE (manual)
   1. Status aktivasi akun: activated / not_activated
   2. Status nomor WA: verified / unverified (jika akun aktif)
   3. Status penerbitan SE: issued / not_issued (jika akun aktif)
   4. Jika issued: active / expired / revoked
   5. Kesesuaian jenis permohonan
          │
          ▼
LANGKAH 2 — Notifikasi berdasarkan keputusan AMS
          │
          ▼
Desktop: WhatsApp Web / mobile: tautan ke aplikasi WhatsApp
→ kirim pesan secara manual → klik “Saya Sudah Mengirim Pesan”
          │
          ├── Pendaftaran Diproses → otomatis Done
          ├── Perbaikan Jenis Permohonan → Returned (setelah dikembalikan)
          └── Penanganan lain → pemeriksaan/tindakan lanjutan → Done bila memenuhi syarat
          │
          ▼
Jika Done → Notifikasi Selesai + tautan survei
          │
          ▼
Pemohon mengisi survei → hasil dapat dilihat administrator
```

**WhatsApp tidak terintegrasi dengan API pengiriman.** Di desktop sistem membuka WhatsApp Web; di mobile sistem mencatat pembukaan lalu menyediakan tombol **Buka Aplikasi WhatsApp** (`whatsapp://`) dan fallback browser. Kedua jalur tetap memerlukan pengiriman pesan dan konfirmasi manual **Saya Sudah Mengirim Pesan**. Membuka tautan tidak membuktikan pesan terkirim/diterima; membuka ulang pesan identik tidak membatalkan konfirmasi yang masih berlaku, sementara perubahan isi notifikasi memerlukan konfirmasi ulang.

### Aturan khusus: Penerbitan SE (`Pendaftaran`)

1. Jika pada AMS akun **belum diaktivasi** (`not_activated`), sistem memilih **Notifikasi 4 — Pendaftaran Diproses**, **bukan** `Aktivasi Akun Diperlukan`.
2. Pesan Pendaftaran Diproses memberikan arahan aktivasi kepada pemohon, tanpa menyatakan SE sudah terbit.
3. Ketika verifikator **mengonfirmasi pengiriman Notifikasi 4** melalui **Saya Sudah Mengirim Pesan**, sistem **otomatis menutup tiket sebagai `Done`** dalam transaksi yang sama.
4. Hasil tersimpan sebagai `actual_process = registration_followup` dan `service_outcome = registration_processed`.
5. Setelah `Done`, verifikator mengirim **Notifikasi Selesai + Survei** secara manual melalui WhatsApp Web (desktop) atau aplikasi WhatsApp (mobile).
6. **Status Done pada skenario ini berarti tindak lanjut administratif selesai, bukan akun sudah aktif atau SE sudah diterbitkan.** Jangan menghitungnya sebagai jumlah sertifikat yang benar-benar terbit.

Alur di atas juga berlaku saat event yang dipilih adalah `registration` pada tiket `Pendaftaran` lain. Jika sistem memilih event berbeda berdasarkan AMS (misalnya `certificate_not_issued`, `certificate_revoked`, atau koreksi layanan), **jangan menganggap tiket otomatis selesai** hanya karena WhatsApp sudah dikonfirmasi.

### Reset Passphrase dan Pembaruan

- Hasil pemeriksaan dimulai dari status **aktivasi akun**, kemudian **WA**, lalu **penerbitan dan keaktifan sertifikat**; tidak boleh langsung menyimpulkan SE aktif hanya dari riwayat pernah terbit.
- Akun belum aktif pada permohonan Reset/Pembaruan menghasilkan notifikasi `Aktivasi Akun Diperlukan`. **Jika setelah tindak lanjut akun telah aktif, WA terverifikasi, dan SE terbit serta aktif**, sistem mengenali riwayat aktivasi pada tiket yang sama dan mengarahkan langsung ke **Tandai Selesai → Notifikasi Selesai + Survei**, tanpa notifikasi Reset/Pembaruan tambahan. Status akhirnya mencatat hasil aktual `certificate_issued`, bukan otomatis `passphrase_reset`/`certificate_renewed`.
- Jika akun sudah aktif tetapi SE **belum terbit**, proses *set passphrase* dan pemeriksaan ulang dilakukan pada **nomor tiket yang sama**. Setelah SE dinyatakan terbit dan aktif serta WA terverifikasi, petugas langsung **Tandai Selesai → Notifikasi Selesai + Survei**.
- Dua jalur tersebut tidak membolehkan penyelesaian jika WA belum terverifikasi atau SE masih belum terbit, expired, atau revoked; keputusan hanya berasal dari pemeriksaan AMS yang dicatat oleh verifikator.
- Jika SE belum terbit, verifikator mengarahkan tindak lanjut *set passphrase* melalui petunjuk resmi BSrE dan dapat melakukan pemeriksaan ulang pada tiket yang sama.
- Jika SE `revoked`, sistem mengarahkan **Penerbitan Ulang** dan tidak menyelesaikan tiket selama belum memenuhi syarat.
- Sertifikat `expired` pada permohonan yang tidak sesuai dapat mengarah ke koreksi **Pembaruan**; sertifikat yang masih expired tidak boleh dianggap selesai.
- Untuk jalur penyelesaian melalui tombol **Tandai Selesai**, backend mensyaratkan akun `activated`, WA `verified`, SE `issued`, dan masa berlaku `active`. Hasil layanan aktual dicatat pada tiket.
- Sistem mencatat apabila konfirmasi notifikasi antara belum ditemukan. Karena itu, prosedur operasional tetap mewajibkan petugas memastikan pesan sudah dikirim sebelum menutup penanganan.

### Permohonan tidak sesuai

Jika hasil AMS dan klarifikasi menunjukkan jenis layanan perlu diajukan ulang, sistem menampilkan notifikasi koreksi. Setelah pesan dikonfirmasi, verifikator menggunakan **Kembalikan Permohonan** sehingga status menjadi **`Returned` / Perlu Pengajuan Ulang**. Tiket lama tidak dihapus, **bukan** `Done`, tidak dihitung sebagai layanan berhasil, dan **tidak** menerima survei selesai.

## 3. Notifikasi WhatsApp dan hasil pelayanan

Saat ini terdapat **11 event/template WhatsApp**, bukan enam seperti dokumentasi lama. Pemilihan event dilakukan berdasarkan kondisi AMS dan konteks tiket; tidak semua event menjadi bagian dari alur normal setiap permohonan.

| Event | Judul notifikasi |
| --- | --- |
| `unverified` | Nomor WA Belum Terverifikasi |
| `reset` | Reset Passphrase |
| `renewal` | Pembaruan Sertifikat |
| `registration` | **Pendaftaran Diproses (Notifikasi 4)** |
| `completed` | **Selesai + Survei (Notifikasi 5)** |
| `unregistered` | User Belum Terdaftar |
| `activation_required` | Aktivasi Akun Diperlukan |
| `certificate_not_issued` | SE Belum Terbit |
| `certificate_revoked` | SE Revoke — Penerbitan Ulang |
| `service_mismatch` | Perbaikan Jenis Permohonan |
| `issuance_required` | Penyesuaian ke Penerbitan SE (Belum Pernah Terbit) |

Administrator dapat menyunting pesan pada **`/admin/wa-templates`**. Preset bawaan hanya dimasukkan jika belum tersedia; seeder ulang tidak menimpa pesan yang sudah diedit.

### Perbedaan status tiket dan hasil aktual

| Nilai hasil aktual (`service_outcome`) | Makna |
| --- | --- |
| `registration_processed` | Permohonan Penerbitan SE telah diproses secara administratif; **bukan bukti SE terbit** |
| `certificate_issued` | SE berhasil diterbitkan |
| `certificate_reissued` | SE berhasil diterbitkan ulang |
| `certificate_renewed` | SE berhasil diperbarui |
| `passphrase_reset` | Reset Passphrase selesai |

Laporan admin menampilkan status tiket, kategori hasil AMS, rekap per layanan/verifikator, rincian berpaginasi, dan ekspor CSV. Filter status pada rincian/CSV tidak mengubah **rekap keseluruhan** periode. Indikator **Selesai Selama Periode** dihitung dari tanggal penyelesaian, termasuk tiket yang dibuat pada periode sebelumnya.

### Survei

- Hanya tersedia bagi tiket `Done` melalui **signed URL** Laravel yang berlaku **30 hari sejak waktu tiket diselesaikan** (`completed_at`). URL stabil ketika pesan akhir yang sama dibuka ulang; bukan diperpanjang setiap kali pesan dibuka.
- Setiap tiket hanya menerima **satu respons survei**.
- Isian: nama responden, empat penilaian skala 1–5 (keseluruhan, petugas, kejelasan, kecepatan), dan saran opsional.
- Hasil tersedia untuk administrator di **`/admin/survei`**.
- Nama responden diisi sendiri; tidak ada verifikasi identitas tambahan khusus survei.
- Mengganti `APP_KEY` atau mengubah URL aplikasi secara tidak terkontrol dapat mengganggu validitas tautan lama.

---

## 4. Arsitektur dan persyaratan

**Komponen utama:** Laravel 13, PHP 8.3+, database SQLite/MySQL/PostgreSQL sesuai driver aplikasi, session/cache/queue Laravel, Google Sheets API (sinkronisasi direktori), SMTP (notifikasi inbox/reset password), dan WhatsApp Web/native (pengiriman manual).

| Komponen | Kebutuhan |
| --- | --- |
| PHP | **8.3 atau lebih baru** yang kompatibel dengan `composer.lock` |
| Ekstensi PHP | Persyaratan Laravel/Composer; khususnya PDO database yang dipilih, `mbstring`, `openssl`, `curl`, `xml`, serta GD untuk validasi tanda tangan PNG |
| Composer | Untuk memasang dependency PHP |
| Database | SQLite untuk instalasi sederhana; MySQL dapat dipakai pada lingkungan lebih besar |
| Web server production | Nginx atau Apache dengan document root **`public/`** |
| Node.js/npm | Tidak wajib untuk menjalankan aset CSS/JS yang saat ini disajikan langsung dari `public/`; dipakai jika membangun aset Vite |
| Google Sheets | Service account dengan hak **Viewer** ke spreadsheet yang relevan |
| SMTP | Diperlukan untuk email tiket baru ke inbox operasional dan tautan reset password petugas |
| Queue worker | Diperlukan bila `QUEUE_CONNECTION=database`; email tiket baru dijalankan sebagai queued notification |

**Tidak ada koneksi API langsung untuk mengubah akun atau sertifikat di AMS BSrE.** Pemeriksaan AMS dan tindak lanjut dilakukan petugas secara manual, lalu hasilnya dicatat pada aplikasi.

## 5. Instalasi lokal

Berlaku untuk macOS, Linux, atau WSL2. Contoh perintah berikut menggunakan shell Bash/Zsh.

### 5.1 Clone dan pasang dependency

```bash
git clone https://github.com/ajung5/TTE-Ticketing.git
cd TTE-Ticketing

php -v
composer --version
composer install
cp .env.example .env
```

Untuk **instalasi baru saja**, buat kunci aplikasi:

```bash
php artisan key:generate
```

> **Jangan jalankan `key:generate` lagi** apabila `.env` dan database berasal dari instalasi yang telah berisi data. Kunci ini digunakan oleh enkripsi kolom dan token aplikasi.

### 5.2 Konfigurasi `.env` lokal

Contoh minimal dengan SQLite:

```dotenv
APP_NAME="TTE Ticketing"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_LOCALE=id
APP_FALLBACK_LOCALE=en

DB_CONNECTION=sqlite
DB_DATABASE=/PATH/ABSOLUT/TTE-Ticketing/database/database.sqlite

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
MAIL_MAILER=log
```

Ganti `DB_DATABASE` dengan path absolut yang benar di komputer Anda. Jika `DB_DATABASE` tidak diisi, `config/database.php` menggunakan file `database/database.sqlite` di direktori proyek.

Buat file SQLite bila belum ada:

```bash
touch database/database.sqlite
php artisan config:clear
php artisan migrate
```

**Alternatif MySQL lokal:** buat database kosong dan ganti bagian koneksi:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tte_ticketing
DB_USERNAME=USER_MYSQL_LOKAL
DB_PASSWORD=PASSWORD_MYSQL_LOKAL
```

### 5.3 Seeder pertama kali: tanpa kredensial di `.env`

**Jalankan pada terminal interaktif** (bukan `--no-interaction`):

```bash
php artisan db:seed --class=DatabaseSeeder
```

Jika role belum ada, `TteBaselineSeeder` akan meminta **akun admin** dan **akun verifikator** berupa email, nama, inisial, password, dan konfirmasi password. Password diketik pada prompt **tersembunyi**. Atur password **12–72 byte**, mengandung huruf besar, huruf kecil, angka, dan simbol.

- Tidak ada password akun default, akun demo, atau variabel `TTE_BASELINE_*` yang perlu dimasukkan ke `.env`.
- Seeder hanya menyimpan **hash password** dalam database.
- Jika akun dengan role tersebut sudah tersedia, provisioning tidak diulang; password dan profil existing tidak diubah.
- Seeder mengisi **11 preset WhatsApp** yang belum ada, tanpa menimpa template kustom.
- Seeder **tidak membuat tiket, survei, atau pegawai dummy**.
- Fresh install tidak membuat tabel `webhook_events` (legacy Zoho); `tickets.source` default `direct`. Kolom `external_submission_id` tetap diperlukan untuk idempotensi form internal.
- Audit migration/seeder dan prosedur instalasi bersih: [`docs/FRESH-INSTALL-DATABASE.md`](docs/FRESH-INSTALL-DATABASE.md).
- Penambahan verifikator setelah bootstrap dilakukan melalui **Manajemen Verifikator** di akun admin.

### 5.4 Sinkronisasi direktori email

Tanpa data direktori yang masih berlaku, pemeriksaan NIK/NIP pada formulir publik tidak akan berhasil. Konfigurasikan Google Sheets sesuai [bagian 8](#8-konfigurasi-google-sheets-email-whatsapp-dan-scheduler), lalu jalankan:

```bash
php artisan tte:sync-email-directory
```

Untuk development, impor CSV dengan header yang sama dapat digunakan:

```bash
php artisan tte:sync-email-directory --csv=/path/ke/direktori-uji.csv
```

Gunakan **data sintetis** pada lingkungan pengujian, kecuali terdapat dasar dan izin pengolahan data nyata.

### 5.5 Jalankan aplikasi dan test

```bash
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```

Buka:

- Formulir: <http://127.0.0.1:8000/permohonan>
- Login petugas: <http://127.0.0.1:8000/login>

Di terminal terpisah:

```bash
php artisan test --compact
php artisan route:list
php artisan schedule:list
```

`phpunit.xml` telah menetapkan **SQLite `:memory:`** dan konfigurasi testing terisolasi. Pastikan ekstensi `pdo_sqlite` tersedia. Bersihkan config cache sebelum menjalankan suite pada mesin developer. Jangan menjalankan test pada database nyata/production.

> Aset yang dipakai UI saat ini berada langsung di `public/css` dan `public/js`; `npm install`/`npm run build` tidak diperlukan untuk sekadar menjalankan tampilan yang ada. Jika suatu saat source aset Vite dikembangkan, jalankan proses build sesuai `package.json`.

---

## 6. Instalasi production baru (VPS dan aaPanel)

Contoh ini menggunakan Linux + aaPanel dan **SQLite**, sesuai pola deployment awal `tte-ticket.my.id`. Untuk domain lain, sesuaikan path. **Ini panduan instalasi baru dengan database kosong; jangan diterapkan mentah-mentah pada database existing.**

### 6.1 Prasyarat server

1. Sediakan PHP 8.3+ dan extension yang lolos `composer check-platform-reqs`.
2. Pasang Composer; pastikan PHP **CLI** dan **PHP-FPM** menjalankan versi/extension yang sesuai.
3. Aktifkan HTTPS/TLS dan arahkan HTTP ke HTTPS.
4. Di pengaturan Website aaPanel, set **document root** ke `/www/wwwroot/tte-ticket.my.id/public` (bukan root proyek).
5. Pastikan proses PHP-FPM memiliki izin tulis ke `storage/`, `bootstrap/cache/`, file SQLite, dan folder SQLite. Jangan memberikan izin tulis luas ke seluruh kode.
6. Simpan kredensial Google **di luar web root** dan jangan commit `.env` ke Git.

### 6.2 Deploy kode dan dependency

Contoh lokasi proyek:

```bash
cd /www/wwwroot
# Clone sekali saja pada server baru (jangan overwrite directory existing):
git clone https://github.com/ajung5/TTE-Ticketing.git tte-ticket.my.id
cd /www/wwwroot/tte-ticket.my.id

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
cp .env.example .env
```

Jika aaPanel sudah membuat direktori situs sebelumnya, sesuaikan langkah cloning dengan lokasi repository yang sebenarnya. Jangan menghapus isi direktori produksi untuk memaksakan `git clone`.

### 6.3 Konfigurasi `.env` production

**Jangan commit contoh berikut sebagai `.env` nyata.** Gunakan secret unik untuk server. `APP_KEY` hanya boleh dibuat pada instalasi baru.

```dotenv
APP_NAME="TTE Ticketing"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tte-ticket.my.id
APP_LOCALE=id
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=sqlite
DB_DATABASE=/www/wwwroot/tte-ticket.my.id/database/database.sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

TTE_SERVICE_NAME="Layanan TTE Kabupaten Subang"
# Versi rilis: baca file VERSION di root (bukan dari .env).
TTE_NEW_TICKET_INBOX_EMAIL=inbox-operasional@domain-anda
TTE_EMAIL_SHEET_ID=ISI_ID_SPREADSHEET
TTE_EMAIL_SHEET_RANGE="Data ASN Merge!A1:R"
TTE_GOOGLE_SERVICE_ACCOUNT_JSON=/etc/tte-ticket/google-credentials.json
TTE_EMAIL_DIRECTORY_MAX_AGE_HOURS=24
TTE_EMAIL_ADMIN_CONTACT_WA=628XXXXXXXXXX

MAIL_MAILER=smtp
MAIL_HOST=SMTP_HOST_ANDA
MAIL_PORT=587
MAIL_USERNAME=SMTP_USERNAME
MAIL_PASSWORD=SMTP_SECRET
MAIL_FROM_ADDRESS=alamat-pengirim@domain-anda
MAIL_FROM_NAME="${APP_NAME}"
```

Isi `APP_KEY` melalui `php artisan key:generate` **hanya** jika instalasi baru dan database masih kosong. Jangan menyalin kunci production dari lingkungan development. `MAIL_MAILER=log` hanya untuk development; SMTP valid diperlukan untuk notifikasi inbox operasional dan reset password. Jika `QUEUE_CONNECTION=database`, pastikan worker dipantau dan berjalan terus-menerus. Versi aplikasi sekarang dibaca dari file Git-tracked `VERSION`; `TTE_APP_VERSION` di `.env` tidak lagi dipakai. Nomor paket patch bukan nomor rilis aplikasi.

**Zona waktu:** `config/app.php` pada commit acuan masih menggunakan `UTC` secara hard-coded. Tampilan dan periode laporan yang relevan menggunakan `Asia/Jakarta` secara eksplisit. Menambahkan `APP_TIMEZONE=Asia/Jakarta` ke `.env` **tidak otomatis** mengubah konfigurasi tersebut tanpa perubahan source.

### 6.4 SQLite: file dan permission yang benar

```bash
cd /www/wwwroot/tte-ticket.my.id
touch database/database.sqlite
ls -ld database
ls -l database/database.sqlite
```

Identifikasi terlebih dahulu user PHP-FPM dari pengaturan aaPanel atau proses OS. **Hanya jika** PHP-FPM benar-benar berjalan sebagai `www`, contoh izin berikut dapat digunakan:

```bash
sudo chown www:www database database/database.sqlite
sudo chmod 750 database
sudo chmod 640 database/database.sqlite
```

Proses PHP-FPM harus dapat **menulis pada file dan folder database**, sebab SQLite dapat membuat file jurnal sementara di folder yang sama. Sesuaikan owner/group serta permission untuk `storage/` dan `bootstrap/cache/` berdasarkan user yang benar. Jangan gunakan `chmod 777` atau `chmod -R 777`.

**Jika sudah ada database:** backup dahulu; jangan memakai `touch`/`chown` sebagai pengganti audit permission dan struktur database. Larangan `migrate:fresh` berlaku mutlak untuk data produksi.

### 6.5 Migration, provisioning akun, dan sinkronisasi

Lakukan secara berurutan:

```bash
cd /www/wwwroot/tte-ticket.my.id

# Hanya instalasi baru: buat APP_KEY satu kali.
php artisan key:generate

php artisan config:clear
php artisan migrate --force

# Harus dijalankan dari terminal SSH/aaPanel yang INTERAKTIF.
# Jangan tambahkan --no-interaction: seeder akan meminta akun awal.
php artisan db:seed --class=DatabaseSeeder --force

# Jika Google Sheets dan service account sudah siap:
php artisan tte:sync-email-directory

php artisan migrate:status
php artisan schedule:list
php artisan about
```

Jangan menjalankan `php artisan key:generate` dua kali. **Jika `.env` sudah mempunyai `APP_KEY` yang sah, lewati baris tersebut.**

Seeder akan membuat admin/verifikator hanya ketika masing-masing role belum tersedia; password dimasukkan secara tersembunyi dan tidak disimpan ke `.env`.

Setelah berhasil dan semua pengujian penerimaan selesai, aktifkan optimasi cache:

```bash
php artisan optimize
```

### 6.6 Scheduler sinkronisasi Google Sheets

Di aaPanel, atur cron **setiap menit** untuk menjalankan Laravel Scheduler (ganti `/PATH/KE/PHP` dengan path executable PHP CLI yang benar pada server):

```cron
* * * * * cd /www/wwwroot/tte-ticket.my.id && /PATH/KE/PHP artisan schedule:run >> /dev/null 2>&1
```

`routes/console.php` menjadwalkan `tte:sync-email-directory` **setiap jam**, hanya jika ID spreadsheet dan path JSON service account dikonfigurasi. Periksa dengan:

```bash
php artisan schedule:list
php artisan tte:sync-email-directory
```

Jika pengaturan `TTE_EMAIL_DIRECTORY_MAX_AGE_HOURS=24`, direktori dianggap tidak tersedia ketika tidak tersinkron dengan sukses selama 24 jam. Pantau kegagalan sinkronisasi; aplikasi tidak otomatis memperbaiki kredensial Google yang dicabut atau spreadsheet yang berubah struktur.

### 6.7 Pemeriksaan setelah deployment

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, dan HTTPS aktif.
- [ ] Document root menunjuk ke `public/`; `.env`, `vendor/`, dan `storage/app/private/` tidak bisa diakses dari web.
- [ ] Halaman `/up`, `/permohonan`, dan `/login` memberi respons yang sesuai.
- [ ] Login admin dan verifikator berhasil; admin tidak bisa mengklaim/mengerjakan tiket.
- [ ] Pencarian NIK/NIP bekerja menggunakan direktori yang masih valid **hanya pada lingkup akses yang diizinkan**.
- [ ] Pengajuan mengeluarkan nomor tiket; tanda tangan tersimpan privat.
- [ ] Self-claim, pemeriksaan AMS, notifikasi WhatsApp, Pendaftaran Diproses → `Done` → survei, dan status `Returned` telah diuji.
- [ ] Email tiket baru diterima **satu inbox operasional** (`TTE_NEW_TICKET_INBOX_EMAIL`) melalui worker queue; email reset password petugas terkirim dari SMTP yang sah.
- [ ] Scheduler dan backup berjalan; proses **restore** pernah diuji.
- [ ] Statistik, pagination, rincian laporan, dan ekspor CSV tampil konsisten.

**Production belum dinyatakan siap hanya karena deployment sukses.** Lakukan UAT dengan data uji dan verifikasi kontrol keamanan sebelum membuka formulir kepada masyarakat/pegawai secara luas.

---

## 7. Pembaruan deployment pada database existing

**Bagian ini berbeda dari instalasi baru.** Repository sekarang mempunyai **9 migration aktif** (3 bawaan Laravel, 4 skema domain, 1 kompatibilitas ukuran `wa_message_templates.event_type`, dan 1 migrasi v6.1 untuk tiket ganda/koreksi email AMS). Riwayat migration lama telah dikonsolidasikan; nama migration yang pernah tercatat dapat tetap ada di tabel `migrations` database existing.

Prinsipnya:

1. Backup database **dan** `storage/app/private`, `.env` serta `APP_KEY` terlebih dahulu. Simpan backup di lokasi aman terpisah.
2. Catat commit terakhir yang sudah berjalan dan periksa perubahan source yang akan dipasang.
3. Jalankan `php artisan migrate:status` dan cocokkan dengan skema aktual; jangan berasumsi migration gabungan yang berubah otomatis memperbaiki tabel lama.
4. Perbarui kode dengan metode deployment yang Anda gunakan dan pasang dependency sesuai `composer.lock`.
5. Jalankan `php artisan optimize:clear`, kemudian `php artisan migrate --force` **setelah** memeriksa migration pending.
6. `php artisan db:seed --class=DatabaseSeeder --force` dapat dijalankan kembali secara interaktif; akun yang sudah ada dan template kustom tidak ditimpa.
7. Periksa proses bisnis pada staging sebelum menerapkan ke pengguna nyata.

**JANGAN jalankan** pada database existing:

```text
php artisan migrate:fresh
php artisan migrate:reset
php artisan db:wipe
php artisan migrate:rollback    # tanpa rencana rollback yang tervalidasi
php artisan key:generate       # jika APP_KEY sudah pernah dipakai untuk data nyata
```

Jika database berasal dari versi sebelum konsolidasi, lakukan **audit skema aktual** dan rencana migrasi khusus. Migration kompatibilitas yang ada tidak menjamin semua variasi skema historis sudah terselesaikan. **Jangan mengganti `DB_CONNECTION=sqlite` menjadi `mysql` tanpa prosedur migrasi data.**

## 8. Konfigurasi Google Sheets, email, WhatsApp, dan scheduler

### Direktori email dinas (Google Sheets)

Header spreadsheet minimal:

```text
NIK atau NIP | Email Gov
```

Header yang juga dikenali antara lain `NAMA`, `NIP`, `JABATAN`, `NAMA UNOR`, `UNIT KERJA`, dan `UPTD`. NIK harus berupa **16 digit**, NIP **18 digit** jika dipakai sebagai identifier, dan kolom spreadsheet perlu diperlakukan sebagai teks agar nol di depan tidak hilang.

Konfigurasi yang dibaca `config/email_directory.php`:

| Variabel | Fungsi |
| --- | --- |
| `TTE_EMAIL_SHEET_ID` | ID Google Spreadsheet |
| `TTE_EMAIL_SHEET_RANGE` | Nama sheet dan rentang, mis. `Data ASN Merge!A1:R` |
| `TTE_GOOGLE_SERVICE_ACCOUNT_JSON` | **Path server** menuju JSON service account; bukan path laptop lokal |
| `TTE_EMAIL_DIRECTORY_MAX_AGE_HOURS` | Batas umur sinkronisasi terakhir; default kode **168 jam** |
| `TTE_EMAIL_ADMIN_CONTACT_WA` | Nomor WA helpdesk pengelola email, format `628...` |

Bagikan Google Sheet kepada email service account sebagai **Viewer**. JSON service account hanya disimpan pada lokasi privat server dengan izin minimum. Aplikasi mengambil data menggunakan Google Sheets API dengan scope `spreadsheets.readonly`.

Jika pemohon memasukkan NIK/NIP yang tidak ditemukan atau tidak memiliki email dinas, aplikasi menampilkan arahan menghubungi pengelola email. Email pada tiket selalu diambil dari direktori, bukan dari input browser.

### Pengamanan lookup NIK/NIP

Pada versi ini, endpoint `/permohonan/cek-nik` menerima NIK/NIP dan dapat mengembalikan **nama, NIP, email dinas lengkap, jabatan, OPD, dan UPTD**. Pembatasan aplikatif yang ada adalah **6 pencarian/menit dan 120/hari per IP**, selain validasi dan session/CSRF. **Tidak terdapat OTP atau validasi email pemohon.**

Karena itu:

- Jangan menganggap rate limit sebagai bukti identitas.
- Untuk operasional terbatas, pertimbangkan akses melalui intranet/VPN atau daftar jaringan yang disetujui pengelola.
- Jika harus dibuka ke internet, tetapkan kontrol tambahan terhadap enumerasi serta evaluasi perlindungan data pribadi sebelum go-live; kebutuhan ini **belum diselesaikan oleh source saat ini**.
- Jika menggunakan reverse proxy/WAF, pastikan IP klien yang diterima Laravel benar; salah konfigurasi IP dapat mengurangi efektivitas rate limit.

### Email tiket baru dan reset password

Saat tiket baru berstatus `Open` diterima dari formulir publik internal Laravel, sistem membuat **satu notifikasi email ke inbox operasional**, bukan mengirim ke seluruh verifikator. Penerima ditentukan oleh `TTE_NEW_TICKET_INBOX_EMAIL` pada `.env`. Isi pesan hanya memuat nomor tiket, jenis layanan, waktu masuk (WIB), status, dan tautan menuju aplikasi; tidak menyertakan NIK, nomor WA, atau tanda tangan. Template berada di `app/Notifications/NewTicketCreatedNotification.php`, sedangkan pengiriman di `app/Services/NewTicketEmailNotifier.php`. Notifikasi ini memakai queue; pastikan worker berjalan dan pantau `php artisan queue:failed`.

Reset password admin/verifikator menggunakan `app/Notifications/TtePasswordResetNotification.php` dan dikirim ke **email akun masing-masing** (bukan inbox operasional). Admin dapat mengirim tautan reset dari **Manajemen Verifikator**. Konfigurasi `MAIL_*` harus menunjuk SMTP valid pada production. Petunjuk rinci: [`docs/PANDUAN-EMAIL-SMTP-DAN-RESET.md`](docs/PANDUAN-EMAIL-SMTP-DAN-RESET.md).

### WhatsApp Web dan aplikasi native

WhatsApp **tidak mengirim pesan otomatis**. Di desktop petugas memakai WhatsApp Web; pada mobile sistem menyiapkan tautan langsung ke aplikasi WhatsApp dan fallback browser setelah pembukaan dicatat. Kirim pesan secara manual, kembali ke tiket, lalu tekan **Saya Sudah Mengirim Pesan**. Template dikelola admin; placeholder seperti `{nama_pemohon}`, `{nomor_tiket}`, `{hasil_aktual}`, dan `{survey_url}` dirender oleh aplikasi.

### Integrasi penerimaan permohonan

Semua permohonan baru diterima melalui formulir internal `GET/POST /permohonan` (`PublicTicketController`). Integrasi webhook Zoho sudah dinonaktifkan dan `POST /api/tickets/zoho` tidak lagi didaftarkan. Pastikan tidak ada sistem eksternal yang masih mengirim permohonan ke endpoint lama. Tiket historis dengan `source = zoho` tetap dapat dibaca.

---

## 9. Pengujian dan pemeriksaan penerimaan

Jalankan pengujian pada **developer/CI/staging dengan database terisolasi**, bukan pada database produksi:

```bash
php artisan optimize:clear
php artisan test --compact
```

Test yang relevan untuk verifikasi perubahan penting:

```bash
php artisan test tests/Feature/Infrastructure/DatabaseAndSeedersTest.php --compact
php artisan test tests/Feature/Tickets/AmsDecisionsTest.php --compact
php artisan test tests/Feature/Notifications/WhatsAppDeliveryTest.php --compact
php artisan test tests/Feature/Reporting/DashboardAndTicketReportsTest.php --compact
php artisan test tests/Feature/Presentation/InternalInterfaceTest.php --compact
php artisan test tests/Feature/Notifications/EmailNotificationsTest.php --compact
php artisan test tests/Feature/Surveys/SurveyWorkflowsTest.php --compact
```

Lakukan juga pemeriksaan dependency dan lingkungan:

```bash
composer check-platform-reqs
composer audit
php artisan about
php artisan migrate:status
php artisan route:list
php artisan schedule:list
```

**Minimal UAT proses bisnis:**

1. Buat permohonan Pendaftaran dengan akun AMS belum aktivasi. Pastikan **Notifikasi 4** muncul.
2. Buka WA Web tanpa mengonfirmasi: tiket tetap `In Progress`.
3. Konfirmasi **Saya Sudah Mengirim Pesan**: tiket `Done`, `service_outcome = registration_processed`, tanpa klaim SE terbit.
4. Pastikan Notifikasi **Selesai + Survei** tersedia, signed URL valid, dan survei hanya bisa diisi sekali.
5. Uji Reset Passphrase/Pembaruan dengan kombinasi akun belum aktivasi, WA belum verified, SE belum terbit, expired, revoked, dan active. Khusus riwayat aktivasi/set passphrase yang setelah pemeriksaan ulang menjadi **WA verified + SE active**, pastikan langsung **Tandai Selesai**, tanpa notifikasi Reset/Pembaruan antara.
6. Uji koreksi layanan hingga status `Returned`; pastikan tidak dihitung sebagai `Done` atau dikirimi survei.
7. Uji pembatasan akses: admin tidak dapat memproses tiket, verifikator kedua tidak bisa mengambil alih tiket milik petugas lain.
8. Uji laporan admin: total per status, **Selesai Selama Periode**, pagination, filter, dan unduhan CSV.

## 10. Operasional, keamanan, dan backup

### Pengamanan dasar

- `APP_ENV=production` dan **`APP_DEBUG=false`** pada server yang dapat diakses pihak lain.
- Wajib HTTPS; konfigurasikan cookie session secure dan document root `public/`.
- Jangan commit `.env`, JSON service account, backup database, dump log yang mengandung identitas, atau file tanda tangan.
- Atur hak akses minimum untuk akun PHP-FPM, database, `storage/`, dan `bootstrap/cache/`.
- Pantau log Laravel, web server, storage, disk, SMTP, dan kegagalan scheduler.
- Terapkan kontrol akses dan retensi data sesuai keputusan pengelola layanan dan kebijakan instansi.
- **`APP_KEY` adalah rahasia kritis.** Kunci ini digunakan dalam enkripsi data NIK/email dan mekanisme aplikasi lain, termasuk penandatanganan URL. Bila pernah terungkap, rencanakan rotasi **beserta migrasi data terenkripsi dan evaluasi hash pencarian**, bukan sekadar menimpa nilainya.

### Backup

Minimal backup terjadwal mencakup:

1. **Database:** SQLite atau database server yang digunakan, termasuk tabel tiket, pengguna, aktivitas, notifikasi, dan survei.
2. **File privat:** terutama `storage/app/private/signatures/`.
3. **Rahasia dan konfigurasi:** `.env`, `APP_KEY`, kredensial service account (disimpan dengan pembatasan akses/backup terenkripsi).
4. **Catatan versi:** commit/tag Git dan prosedur pemulihan deployment.

Untuk SQLite, gunakan mekanisme backup yang konsisten, misalnya `.backup` dari utilitas `sqlite3` (bukan sekadar menyalin file yang sedang aktif tanpa memahami jurnalnya):

```bash
# Contoh, setelah menentukan lokasi backup aman di luar web root:
sqlite3 database/database.sqlite ".backup '/LOKASI-BACKUP-AMAN/tte-ticketing.sqlite'"
```

Uji **restore** pada lingkungan terpisah, termasuk akses terhadap data terenkripsi dengan kunci yang benar. Jangan menyimpan backup di direktori `public/`.

### Release dan rollback

Gunakan commit/tag yang telah diuji; jalankan deployment secara terkontrol. Saat rollback, pertimbangkan **kesesuaian source dengan skema database**, bukan hanya melakukan `git checkout`. Database existing tidak boleh di-reset untuk mengatasi error tampilan atau seeder.

## 11. Troubleshooting

| Gejala | Penyebab yang umum | Pemeriksaan/tindakan aman |
| --- | --- | --- |
| `attempt to write a readonly database` | PHP-FPM tidak dapat menulis SQLite atau folder `database/` | Periksa owner, izin file dan folder, filesystem, disk penuh, jurnal SQLite; gunakan user PHP-FPM yang benar |
| `Baseline memerlukan minimal satu admin dan satu verifikator` | Role belum ada dan provisioning belum dilakukan | Jalankan **seeder interaktif** pada terminal SSH/aaPanel, bukan `--no-interaction`; tidak perlu `TTE_BASELINE_*` di `.env` |
| Seeder tidak meminta password | Akun dengan role tersebut sudah tersedia; atau terminal tidak interaktif | Periksa jumlah akun dan gunakan terminal interaktif jika belum ada |
| `500 Internal Server Error` | `.env`, kunci, cache, dependency, permission, database | Periksa `storage/logs/laravel.log`; jangan mengaktifkan `APP_DEBUG=true` di server publik |
| Halaman 404 / seluruh URL bermasalah | Web root/rewrite salah | Pastikan document root `public/`; periksa konfigurasi Nginx/Apache |
| NIK/NIP tidak ditemukan | Direktori belum sinkron, data tidak cocok, atau email dinas kosong | Jalankan `tte:sync-email-directory`, cek header/range dan umur sinkronisasi |
| Google Sheet gagal dibaca | Path JSON masih menunjuk laptop, sheet belum dibagikan, izin Google API | Gunakan path **Linux server** yang dapat dibaca PHP; service account sebagai Viewer |
| Reset password tidak terkirim | `MAIL_MAILER=log` atau SMTP gagal | Uji SMTP dari staging, cek email pengirim dan log |
| Tombol **Saya Sudah Mengirim Pesan** tidak muncul | URL WhatsApp belum dibuka lewat alur aplikasi atau halaman belum memuat ulang | Gunakan tombol WhatsApp dari tiket (di mobile pilih **Buka Aplikasi WhatsApp**), kembali/refresh halaman, lalu cek `opened_at` jika perlu |
| Survei 404 / 429 | Tiket belum `Done`, signed URL tidak valid/kedaluwarsa, atau rate limit | Periksa status tiket, URL/domain/`APP_KEY`, dan header `Retry-After` |
| Test gagal pada source production | Perubahan source/test atau environment tidak sinkron | Jalankan pada staging/CI terisolasi; **jangan** menjalankan `migrate:fresh` di production |

Untuk diagnosis dasar di server:

```bash
php artisan about
php artisan migrate:status
php artisan schedule:list
tail -n 80 storage/logs/laravel.log
```

**Jangan mengunggah log mentah yang memuat `APP_KEY`, token, cookie, data pribadi, atau kredensial.**

## 12. Rute dan struktur proyek

### Rute utama

| URL | Fungsi | Akses |
| --- | --- | --- |
| `/permohonan` | Formulir permohonan | Publik |
| `/permohonan/cek-nik` | Pencarian direktori NIK/NIP (POST) | Publik, dibatasi rate limit |
| `/permohonan/berhasil` | Konfirmasi pengajuan | Session pemohon |
| `/login` | Login petugas | Publik |
| `/tickets` | Antrean dan filter tiket | Verifikator |
| `/tickets/{ticket}` | Detail, pemeriksaan AMS, WhatsApp, workflow | Verifikator yang berhak |
| `/admin` | Statistik layanan | Admin |
| `/admin/verifiers` | Manajemen verifikator | Admin |
| `/admin/laporan-tiket` | Laporan dan filter periode | Admin |
| `/admin/laporan-tiket/csv` | Ekspor seluruh hasil filter | Admin |
| `/admin/wa-templates` | Preset notifikasi | Admin |
| `/admin/survei` | Hasil survei | Admin |
| `/survei/{ticket}` | Survei melalui signed URL | Pemegang tautan sah, tiket `Done` |
| `/up` | Health check framework | Sesuai akses server |

### Struktur penting

```text
app/
  Console/Commands/SyncEmailDirectory.php
  Http/Controllers/
    PublicTicketController.php
    TicketController.php
    PublicSurveyController.php
    AdminController.php
    AdminTicketReportController.php
    VerifierManagementController.php
    WaTemplateController.php
  Models/
  Services/
    EmailDirectoryService.php
    EmailDirectorySync.php
    GoogleSheetDirectoryReader.php
    WhatsAppMessage.php
config/
  app.php
  database.php
  email_directory.php
  filesystems.php
  tte.php          # termasuk TTE_APP_VERSION dan inbox notifikasi
database/
  migrations/         # 8 migration aktif
  seeders/
    DatabaseSeeder.php
    TteBaselineSeeder.php
    WaTemplatePresetsSeeder.php
public/
  css/
  js/
resources/views/
  layouts/footer.blade.php
  public/
  tickets/
  admin/
routes/
  web.php
  api.php
  console.php
tests/Feature/
```

---

## 13. Dokumentasi operasional yang dipertahankan

Folder `docs/` dibersihkan dari petunjuk patch historis yang tidak lagi berlaku setelah implementasi v58–v60.1. Dokumentasi acuan saat ini adalah **README ini** dan berkas berikut:

| Berkas | Kegunaan |
| --- | --- |
| [`PANDUAN-EMAIL-SMTP-DAN-RESET.md`](docs/PANDUAN-EMAIL-SMTP-DAN-RESET.md) | Setup SMTP, mailbox tiket baru, queue, reset password dan smoke test |
| [`CONTOH-DIREKTORI-EMAIL-TEST.csv`](docs/CONTOH-DIREKTORI-EMAIL-TEST.csv) | Contoh data **sintetis** untuk menguji impor direktori, bukan data pegawai nyata |

Panduan patch yang dihapus tetap tersedia dalam riwayat Git. Pembersihan integrasi legacy tidak menghapus data aplikasi, migration, seeder, atau tiket historis.

---

## Catatan terakhir

Panduan dasar README ini berasal dari pemeriksaan **5 November 2026** (baseline `26504c2`), sedangkan bagian **Pembaruan implementasi** mencatat perubahan sampai **10 November 2026** (commit `1e842a5`). Pembaruan dokumentasi **tidak menjalankan** migration, test suite, deployment, ataupun pemeriksaan production. Uji perubahan proses bisnis dan keamanan secara terpisah sebelum rilis.
