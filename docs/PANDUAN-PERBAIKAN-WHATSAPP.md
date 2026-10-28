# Patch WhatsApp Web – TTE-Ticketing

## Masalah

Tombol WhatsApp sebelumnya membuat tab baru (`target="_blank"`) dan mengarah melalui `wa.me`, yang dapat memicu prompt membuka aplikasi WhatsApp.

## Isi perubahan (hanya 2 file aplikasi + 1 test baru)

- `app/Services/WhatsAppMessage.php` — URL diubah menjadi `https://web.whatsapp.com/send?phone=...&text=...`.
- `resources/views/tickets/show.blade.php` — dua tombol WhatsApp menggunakan target bernama `tte_whatsapp_web` alih-alih `_blank`.
- `tests/Feature/WhatsAppWebLinkTest.php` — tes pembentukan URL dan tombol tindak lanjut/selesai.

## Pemasangan di MacBook

Ekstrak ZIP lalu gabungkan isi folder ke **root** proyek Laravel. Jangan mengganti seluruh folder `app`, `resources`, atau `tests`; salin file sesuai path di atas.

```sh
cd /Users/sandi/Dev/TTE-Ticketing
php artisan optimize:clear
php artisan test tests/Feature/WhatsAppWebLinkTest.php
php artisan serve --host=127.0.0.1 --port=8000
```

Tidak perlu migrasi atau `composer install` khusus patch ini.

## Perilaku

Klik WhatsApp dari detail tiket. Tautan akan menuju WhatsApp Web langsung, dengan isi pesan dari layanan dan verifikator yang sudah ada. Pertama kali dapat membuka satu tab WhatsApp khusus; untuk klik selanjutnya, browser dapat menggunakan kembali tab tersebut **jika** kebijakan browser dan WhatsApp mengizinkan.

**Tidak ada mekanisme standar yang bisa menjamin bahwa tab WhatsApp Web yang sudah dibuka manual akan otomatis digunakan oleh aplikasi Laravel.** Jika browser tetap membuka tab baru pada setiap klik, gunakan opsi di bawah.

### Opsi paling pasti: tetap pada tab yang sama (tanpa tab baru)

Pada `resources/views/tickets/show.blade.php`, ganti dua `target="tte_whatsapp_web"` menjadi `target="_self"`. Ini menjamin proses navigasi tetap pada tab Laravel saat ini, namun halaman Laravel tergantikan oleh WhatsApp Web; tekan Back untuk kembali. Pengujian target bernama pada file test perlu disesuaikan jika opsi ini dipilih.

## Catatan

- WhatsApp Web harus login pada profil browser yang sama.
- Pengiriman pesan **belum otomatis**; petugas tetap menekan Send dan konfirmasi manual dalam aplikasi.
- Pengujian fitur di atas disediakan untuk dijalankan pada instalasi Laravel lokal; lingkungan pembuatan paket tidak menyertakan dependency `vendor` proyek.
