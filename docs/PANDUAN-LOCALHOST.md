# Panduan Localhost — TTE-Ticketing (Fresh Laravel 13)

**Baseline yang diperiksa**: `https://github.com/ajung5/TTE-Ticketing` branch `main`, 28 Oktober 2026. Fresh Laravel `^13.17`, PHP `^8.3`, **tanpa Laravel Sanctum**.

Paket ZIP ini **hanya berisi file baru/diubah**, bukan aplikasi Laravel utuh. **Jangan menambahkan paket ZIP yang dibuat untuk repo `tte-manajemen` lama** karena migrasinya mengasumsikan tabel `tickets` sudah ada. Paket ini khusus fresh install, sudah membuat tabel `tickets` dari awal dan mengaktifkan routing `routes/api.php`.

## 1. Periksa prasyarat MacBook

```bash
php -v
composer -V
php -m | grep -Ei 'pdo_sqlite|pdo_mysql|sqlite3'
```

Disarankan PHP 8.3+ dan Composer. Node/npm **tidak wajib** untuk tampilan paket ini (CSS tersedia di `public/css/tte.css`). Tidak menggunakan Docker maupun dependensi WhatsApp berbayar.

## 2. Pastikan berada di folder repo baru (bukan repo sebelumnya)

```bash
cd /lokasi/proyek/TTE-Ticketing
git remote -v
git status
```

`git remote -v` seharusnya menunjukkan `github.com/ajung5/TTE-Ticketing.git`. Simpan perubahan lokal atau buat branch sebelum menyalin paket:

```bash
git switch -c feature/tte-ticketing-mvp
```

Ekstrak ZIP kemudian **salin isi di dalam ZIP** ke root folder `TTE-Ticketing`. Jangan menyalin folder pembungkus `TTE-Ticketing-fresh-MVP-patch` sebagai subfolder di proyek.

Contoh:

```text
TTE-Ticketing/
  app/Http/Controllers/AuthController.php
  app/Http/Controllers/TicketController.php
  app/Models/Ticket.php
  bootstrap/app.php
  database/migrations/2026_10_28_220000_create_tte_ticketing_tables.php
  public/css/tte.css
  resources/views/layouts/app.blade.php
  routes/web.php
  routes/api.php
  artisan
```

File Laravel lain tetap dipertahankan. Khusus `bootstrap/app.php`, `routes/web.php`, `app/Models/User.php`, dan `database/seeders/DatabaseSeeder.php`: **pilih Replace** dengan versi paket ini setelah memastikan working tree belum berisi pekerjaan penting.

## 3. Instal dependensi dan konfigurasi environment

```bash
composer install
cp .env.example .env
php artisan key:generate
```

> `cp .env.example .env` dan `php artisan key:generate` **hanya** jika `.env` belum ada atau APP_KEY belum tersedia. Jika fresh install sudah punya `.env`, jangan timpa/ubah APP_KEY sembarangan.

Edit `.env` dan set:

```dotenv
APP_NAME="TTE Ticketing"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_LOCALE=id
DB_CONNECTION=sqlite
SESSION_DRIVER=database
MAIL_MAILER=log
ZOHO_WEBHOOK_SECRET=ISI_DENGAN_SECRET_ACAK_MINIMAL_32_KARAKTER
TTE_SERVICE_NAME="Layanan TTE Kabupaten Subang"
TTE_DEMO_PASSWORD="GantiDenganPasswordDemoKuat!2026"
```

Buat webhook secret yang kuat:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Pastikan hanya satu baris `DB_CONNECTION` yang aktif. Jika ada `DB_URL` atau variabel database lain yang menunjuk ke database berbeda, hapus/komentari konfigurasi yang bertentangan (tanpa menghapus data asli).

## 4. Siapkan database (SQLite gratis dan paling mudah untuk localhost)

```bash
mkdir -p database
touch database/database.sqlite
php artisan config:clear
php artisan migrate
php artisan db:seed
```

**Jangan gunakan `migrate:fresh` pada database yang berisi data penting.** Migration pada paket ini membuat tabel tiket dari awal, dan hanya menambahkan dua kolom ke `users`.

Opsional MySQL:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tte_ticketing
DB_USERNAME=root
DB_PASSWORD=
```

Buat terlebih dahulu database kosong dengan nama `tte_ticketing`, kemudian jalankan ulang `php artisan config:clear && php artisan migrate && php artisan db:seed`.

## 5. Jalankan aplikasi di Mac

```bash
php artisan optimize:clear
php artisan route:list
php artisan serve --host=127.0.0.1 --port=8000
```

Akses `http://127.0.0.1:8000/` → redirect ke `/login`. Route yang wajib terlihat:

```text
GET|HEAD   /
GET|HEAD   login
POST       login
GET|HEAD   dashboard
GET|HEAD   tickets
POST       tickets/{ticket}/claim
GET|HEAD   admin
POST       api/tickets/zoho
```

Jika 8000 dipakai aplikasi lain, gunakan `--port=8001` dan sesuaikan `APP_URL` di `.env`.

**Akun demo lokal:**

| Role | Email | Password |
|---|---|---|
| Admin | `admin@tte.test` | Nilai `TTE_DEMO_PASSWORD` di `.env` |
| Verifikator A | `verifikator1@tte.test` | Sama |
| Verifikator B | `verifikator2@tte.test` | Sama |

Seeder hanya berjalan jika `APP_ENV=local`, aman dijalankan ulang tanpa mengganti password akun yang sudah ada. Jangan memakai akun/data demo untuk produksi. Nomor WA di tiket demo bukan nomor sungguhan; **jangan kirim pesan ke nomor demo**.

## 6. Pengujian mandiri

1. Login verifikator A, buka antrean, pilih tiket dengan status `Baru`, lalu tekan **Ambil Tiket**.
2. Login verifikator B pada browser lain; tiket yang diambil A tidak muncul lagi di antrean B. B tidak dapat mengubah tiket A meski mengetahui URL langsung.
3. Verifikator A mencatat tindak lanjut (min. 5 karakter), kemudian isi hasil penyelesaian (min. 10 karakter) sebelum menekan **Tandai Selesai**.
4. Klik **Buka WhatsApp dengan Pesan** untuk melihat pesan yang terisi sesuai layanan dan nama/inisial verifikator; tidak ada pengiriman otomatis oleh server.
5. Login admin; admin hanya melihat statistik serta tombol **Kirim Link Reset Password**. Admin tidak memiliki akses pengambilan, pengubahan, atau penyelesaian tiket.

Jalankan test:

```bash
php artisan test tests/Feature/TteManagementTest.php
```

## 7. Simulasi webhook tanpa Zoho asli

Ganti `<SECRET>` dengan nilai `ZOHO_WEBHOOK_SECRET` di `.env`:

```bash
curl -i -X POST http://127.0.0.1:8000/api/tickets/zoho \
  -H "Content-Type: application/json" \
  -H "X-Zoho-Webhook-Secret: <SECRET>" \
  -d '{"submission_id":"local-test-001","nama_user":"Pemohon Pengujian","no_wa":"081234567890","layanan":"Reset Passphrase","opd":"OPD Contoh"}'
```

Respons pertama `201 Created`. Mengirim ulang `submission_id` yang sama menghasilkan `200 OK` dengan `duplicate:true`, tanpa tiket duplikat. Secret salah menghasilkan `401`, atau `503` jika belum dikonfigurasi.

Cloud Zoho **tidak dapat mengakses** localhost. Untuk integrasi nyata dibutuhkan endpoint HTTPS publik melalui infrastruktur instansi atau tunnel sementara yang disetujui keamanan. Mapping JSON Zoho wajib menggunakan key `submission_id`, `nama_user`, `no_wa`, dan `layanan`. Kolom opsional: `nip`, `opd`, `jabatan`, `email`, `keterangan`. Nilai `layanan` harus salah satu `Reset Passphrase`, `Pembaruan`, `Pendaftaran`. Baca `docs/zoho-payload.example.json`.

Webhook saat ini memakai shared secret di header `X-Zoho-Webhook-Secret`, bukan tanda tangan HMAC. Jangan mengirim passphrase, password BSrE, OTP, atau data identitas berlebihan. Jika kebijakan instansi mensyaratkan HMAC atau IP allowlist, pasang gateway sesuai ketentuan sebelum penggunaan produksi.

## 8. Reset password dan notifikasi

Admin dapat memicu email reset password untuk verifikator. `MAIL_MAILER=log` berarti link reset dicatat dalam `storage/logs/laravel.log` (tidak terkirim lewat email). Di lingkungan produksi, ganti ke SMTP resmi dan atur kredensial aman.

WhatsApp menggunakan Click-to-Chat gratis: sistem menyiapkan caption, lalu verifikator menekan tombol kirim di WhatsApp. Konfirmasi manual pada UI bukan bukti pesan telah diterima.

## 9. Troubleshooting

```bash
pwd
git remote -v
php artisan route:list
php artisan migrate:status
lsof -nP -iTCP:8000 -sTCP:LISTEN
```

- `404 /login`: file `routes/web.php` belum disalin ke **root repo baru** atau route cache masih lama; jalankan `php artisan optimize:clear`.
- `404 /api/tickets/zoho`: file `bootstrap/app.php` belum diganti (harus mencantumkan `api: __DIR__.'/../routes/api.php'`).
- `could not find driver`: ekstensi `pdo_sqlite`/`pdo_mysql` belum aktif.
- `no such table: tickets`: migration belum berjalan di database yang dipakai aplikasi.
- `419 Page Expired`: masalah session/CSRF; cek `APP_KEY`, `APP_URL`, dan refresh form.
- `401 Unauthorized` webhook: cek header secret dan `php artisan config:clear`.
- `500` internal: lihat `storage/logs/laravel.log`.
- Port 8000 dipakai: cek PID dengan `lsof -nP -iTCP:8000 -sTCP:LISTEN` atau jalankan Laravel di port 8001.

## 10. Batasan versi MVP

- Baru tiga status: `Open`, `In Progress`, `Done`.
- Self-claim, bukan assignment admin; tiket yang sudah memiliki catatan tindak lanjut tidak dapat dilepaskan sendiri.
- Tidak ada WhatsApp Cloud API dan tidak ada bukti delivery provider.
- Belum ada fitur tambah/hapus verifikator melalui UI, SLA, auto-escalation, dan proses penolakan/perbaikan berkas.
- Sebelum go-live, lakukan audit keamanan, backup, retensi data, SMTP, HTTPS, dan uji alur bisnis sesuai SOP BSrE.
