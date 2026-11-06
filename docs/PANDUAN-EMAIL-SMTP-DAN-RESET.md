# Email SMTP Gmail dan reset password - TTE Ticketing v55

## Fitur

- Satu inbox operasional menerima pemberitahuan satu kali untuk setiap tiket **baru** (baik form publik maupun webhook legacy). Tidak mengirim ke semua verifikator.
- Email tiket berisi nomor tiket, jenis layanan, waktu (WIB), status, dan tombol menuju aplikasi. Tidak memuat NIK, NIP, WA, nama pemohon, maupun tanda tangan.
- Admin dan verifikator dapat memakai **Lupa Password?** pada halaman login. Admin juga dapat mengirim tautan reset ke verifikator dari **Manajemen Verifikator**.
- Tautan reset dikirim ke **email masing-masing akun**, bukan ke inbox operasional. Token Laravel berlaku 60 menit, satu kali pakai, dan dibatasi penerbitannya.
- Tidak ada perubahan migration, seeder, RBAC, ataupun proses bisnis tiket.

## Konfigurasi lokal / produksi (.env)

Buat **App Password** 16 karakter pada akun Gmail khusus pengirim (Verifikasi 2 Langkah harus aktif): https://myaccount.google.com/apppasswords . Tersedia hanya pada akun Google yang memenuhi syarat; admin Google Workspace dapat melarangnya.

Contoh (ganti dengan nilai asli di `.env` saja, JANGAN DI-COMMIT):

```dotenv
APP_URL=https://tte-ticket.my.id
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME="alamat-pengirim@gmail.com"
MAIL_PASSWORD="APP_PASSWORD_16_KARAKTER"
MAIL_FROM_ADDRESS="alamat-pengirim@gmail.com"
MAIL_FROM_NAME="TTE Ticketing"
TTE_NEW_TICKET_INBOX_EMAIL="inbox-tiket@gmail.com"
QUEUE_CONNECTION=database
```

**Catatan:** `MAIL_FROM_ADDRESS` idealnya sesuai `MAIL_USERNAME`, agar Gmail mengizinkan pengiriman. Inbox tiket dapat berbeda dari Gmail pengirim. Email akun admin/verifikator berasal dari tabel `users`, bukan `.env` dan bukan `TTE_NEW_TICKET_INBOX_EMAIL`.

Untuk pengembangan tanpa pengiriman nyata, `MAIL_MAILER=log` menyimpan isi email, **termasuk tautan reset yang sensitif**, di `storage/logs/laravel.log`. Jangan gunakan `log` atau fallback `failover` ke `log` pada production. Untuk tes otomatis, `phpunit.xml` memakai `MAIL_MAILER=array` agar tidak mengirim email sungguhan.

## Aktivasi

```bash
php artisan optimize:clear
php artisan route:list --name=password
# Jika QUEUE_CONNECTION=database, jalankan worker secara permanen (systemd/supervisor di production):
php artisan queue:work database --sleep=3 --tries=3 --timeout=60
```

**Tidak ada migration baru** dalam patch. Tabel `password_reset_tokens` dan `jobs` sudah berasal dari migration Laravel yang ada. Bila migrasi di sistem target belum dijalankan, jalankan `php artisan migrate --force` setelah memeriksa migrasi yang belum diterapkan.

## Smoke test pengiriman SMTP

Jalankan `php artisan tinker`, lalu kirim satu email sederhana ke inbox operasional:

```php
use Illuminate\Support\Facades\Mail;
Mail::raw('Pengujian SMTP TTE Ticketing.', function ($message) {
    $message->to(config('tte.new_ticket_inbox_email'))
        ->subject('TES SMTP - TTE Ticketing');
});
```

Jangan mengetikkan atau mengirimkan `MAIL_PASSWORD` ke chat, tiket, atau log. Gunakan alamat tes resmi, bukan data pegawai asli.

1. Buka `/lupa-password`, masukkan alamat email **akun admin** atau **verifikator** yang ada pada `users.email`.
2. Periksa inbox/spam penerima, klik tautan sekali pakai, buat password baru minimal 12 karakter, huruf besar/kecil, dan angka.
3. Buat permohonan dummy melalui form publik: email masuk ke `TTE_NEW_TICKET_INBOX_EMAIL`, bukan ke semua verifikator.
4. Periksa worker queue dengan `php artisan queue:failed` dan log Laravel apabila notifikasi tiket terlambat. Reset password sengaja tidak melalui queue agar gangguan SMTP diketahui saat diminta.
5. Pastikan `APP_URL` menggunakan HTTPS dan domain nyata, konfigurasi host virtual server membatasi Host header yang sah. Tautan reset yang salah domain/HTTP akan gagal atau berisiko.

## Tests

```bash
php artisan test tests/Feature/NewTicketEmailNotificationTest.php --compact
php artisan test tests/Feature/TteEmailAndPasswordResetTest.php --compact
php artisan test --compact
```

Semua test otomatis menggunakan mail fake/array. Semua percobaan instalasi tetap memerlukan **SMTP nyata** untuk memastikan delivery end-to-end ke Gmail.
