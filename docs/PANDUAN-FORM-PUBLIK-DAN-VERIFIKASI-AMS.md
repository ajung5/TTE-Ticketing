# Form Publik TTE & Pemeriksaan WhatsApp AMS BSrE (v3)

Basis: repository GitHub `ajung5/TTE-Ticketing`, branch `main`, commit `208c4de` (29 Oktober 2026); memuat patch UI/preset v2.

## Instalasi

1. Backup database dan kode. Salin seluruh isi ZIP ke root proyek **dengan merge**, bukan mengganti folder `app`/`resources` secara utuh.
2. Jalankan `php artisan optimize:clear` dan `php artisan migrate`.
3. Periksa `APP_KEY` tetap dan tersimpan dengan aman. Cast `nik` = `encrypted` tidak kompatibel jika APP_KEY diganti atau hilang.
4. Jalankan `php artisan test tests/Feature/PublicRequestAndAmsVerificationTest.php` dan `php artisan test`.
5. Jalankan `php artisan serve` untuk local, lalu kunjungi `/permohonan`, `/login`, `/admin/wa-templates`.

## Proses

- Pemohon: GET `/permohonan`, POST `/permohonan` dengan CSRF, token idempoten per formulir, dan throttle 5 submission/10 menit per IP; tanpa login. Halaman berhasil hanya menunjukkan nomor tiket dan tidak mengekspos detail lewat URL.
- Pemohon memasukkan `nama_user`, NIK 16 digit, email dinas `@subang.go.id`, nomor WA, OPD, jabatan, serta persetujuan. NIP dan keterangan opsional. Jangan kumpulkan OTP/passphrase/password.
- NIK terenkripsi di database. Di halaman tiket dan pesan WA NIK hanya ditampilkan 12 digit tersamarkan + 4 digit akhir; `{nik}` juga tersamarkan.
- Verifikator mengambil tiket, memeriksa status nomor pada **portal resmi AMS BSrE**, kemudian mencatat `verified` / `unverified` dan catatan pemeriksaan. `unchecked` adalah default sampai pemeriksaan.
- `unverified` -> pesan WA awal (disesuaikan preset admin), tiket tetap `In Progress`. Periksa ulang hingga `verified` untuk melanjutkan pesan tindak lanjut/menandai selesai.
- `verified` -> catatan tindak lanjut, WA followup, penyelesaian, WA selesai. Pesan tidak dikirim otomatis: Click-to-Chat + konfirmasi manual.
- Tiket `simulated`: tidak boleh membuka WhatsApp nyata; preview dan salin pesan tetap tersedia.
- Admin dapat memilih layanan dan fase pesan (9 preset: 3 layanan x 3 fase), mengetik placeholder manual atau memilih tombol token untuk memasukkan ke textarea yang sedang difokuskan. Hanya placeholder dari daftar yang diizinkan; audit perubahan tetap ada.
- Zoho webhook **masih aktif** sebagai alternatif dan `nik` opsional untuk kompatibilitas dengan konfigurasi lama.

## Penting sebelum membuka form untuk publik

- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`, backup terenkripsi, kontrol retensi/penghapusan data, update patch keamanan server.
- Tambahkan CAPTCHA/Turnstile jika bot mulai menyerang, plus WAF/rate limit pada reverse proxy. Rate limit aplikasi saja tidak cukup untuk layanan publik skala besar.
- Form NIK mengandung data pribadi; pastikan dasar pemrosesan, pemberitahuan privasi yang disahkan OPD, minimisasi dan retensi sebelum produksi.
- Pemeriksaan AMS *manual*; ini tidak mengakses API BSrE atau memverifikasi secara otomatis. Jangan menuliskan OTP di catatan.
- Untuk data real: jangan jalankan `db:seed` dengan demo/simulasi.

## Potensi migrasi data v2

- Tiket lama memiliki `wa_verification_status=unchecked` setelah migrasi. Harus diperiksa ulang oleh verifikator sebelum mengirim WA atau ditandai selesai (tiket lama berstatus Done tidak diubah, tetapi verifikator dapat mencatat hasil verifikasi AMS terlebih dahulu sebelum mengirim pesan selesai). Ini disengaja.
- Seeder dummy lama yang sudah berjalan tidak mengubah barisnya saat di-run ulang. Untuk latihan, verifikator bisa memeriksa status ulang melalui detail tiket. Tombol WA nyata tetap dinonaktifkan untuk `source=simulated`.
- Jika perubahan template menggunakan token `{nik}`, yang tampil pada WA hanya NIK tersamarkan. Jangan menampilkan 16 digit penuh dalam pesan WhatsApp.
