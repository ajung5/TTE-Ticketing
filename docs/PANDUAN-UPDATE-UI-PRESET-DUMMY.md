# Patch TTE Ticketing — UI Modern + 100 Dummy + Preset WhatsApp

## Baseline

Repository `https://github.com/ajung5/TTE-Ticketing`, branch `main`, commit `d806883e24fc0e81c1f00459d8aa8fe4664e6d21` (29 Oktober 2026, saat audit). Patch hanya berisi file **baru dan berubah**. Tidak mengganti file inti seperti `app/Http/Controllers/Controller.php`.

## Sebelum memasang

1. Cadangkan database lokal (`database/database.sqlite` jika memakai SQLite) dan commit perubahan lokal.
2. Ekstrak ZIP lalu **merge isi foldernya** ke root repo, jangan mengganti seluruh direktori `app`, `routes`, atau `resources` (jangan sampai `app/Http/Controllers/Controller.php` terhapus).
3. Jangan copy file `.env` dari ZIP: memang tidak disertakan.

## Instalasi MacBook / localhost

```bash
cd /Users/sandi/Dev/TTE-Ticketing
php artisan optimize:clear
php artisan migrate
php artisan db:seed --class=DemoTicketsSeeder
php artisan route:list --path=admin
php artisan serve --host=127.0.0.1 --port=8000
```

- `php artisan migrate` membuat tabel `wa_message_templates` dan **6 preset awal**: 3 layanan x 2 tahap (tindak lanjut/selesai).
- `php artisan db:seed --class=DemoTicketsSeeder` membuat **100 tiket dummy tambahan** dengan ID `TTE-SIMULASI-001` s.d. `TTE-SIMULASI-100`. Seeder aman diulang dan tidak menghapus atau menimpa tiket Zoho. Hanya berjalan jika `APP_ENV=local`.
- `php artisan db:seed` biasa juga memanggil seeder ini setelah membuat akun demo, tetapi **jangan jalankan seeder demo pada production**.
- Untuk memeriksa jumlah data simulasi:

```bash
php artisan tinker --execute="echo App\\Models\\Ticket::where('source','simulated')->count();"
```

Seharusnya 100. Tiga tiket demo awal `source=demo` tetap ada, jadi jumlah tiket keseluruhan mungkin lebih dari 100. Jika database sebelumnya sudah berisi data Zoho, data itu dipertahankan.

## Fitur

**Admin** (role `admin`):
- Halaman `http://127.0.0.1:8000/admin`: statistik, akun verifikator, reset password.
- Halaman `http://127.0.0.1:8000/admin/wa-templates`: enam **preset redaksi yang dapat diedit dan disimpan**, satu untuk kombinasi jenis layanan + tahap (followup/completed). Ini bukan editor unlimited banyak variasi per kategori.
- Admin **tidak** dapat melihat detail permohonan atau mengklaim tiket.

**Verifikator** (role `verifier`):
- Antrean tiket dengan penanda `DATA SIMULASI` untuk data pengujian.
- Saat tiket diproses dan sudah memiliki catatan tindak lanjut, tampil pratinjau WhatsApp dengan tombol **Salin Pesan**.
- Saat selesai, tampil pratinjau WhatsApp selesai dengan tombol **Salin Pesan**.
- Copy tidak mengirim WhatsApp dan tidak menandai konfirmasi terkirim.
- Tiket `source=simulated` **tidak boleh membuka WhatsApp Web** agar nomor fiktif tidak dipakai untuk pengiriman; pratinjau dan copy tetap dapat dicoba. Tiket dari Zoho tetap dapat membuka WA Web seperti sebelumnya.
- Pengiriman WA masih manual dengan browser dan tidak dijamin menggunakan tab WhatsApp yang sudah dibuka sendiri.

## Placeholder preset

- `{nama_pemohon}` **wajib**
- `{nomor_tiket}` **wajib**
- `{nama_verifikator}` **wajib**
- `{inisial_verifikator}`
- `{layanan}`
- `{nama_instansi}`
- `{opd}`

Sistem akan menolak placeholder yang tidak dikenal. Jangan menulis permintaan passphrase/OTP/password dalam template. Template baru berlaku saat pesan dihasilkan berikutnya. `wa_notifications` yang pernah dicatat menyimpan versi pesan sebelumnya untuk audit.

## Test

```bash
php artisan test tests/Feature/WaTemplateManagementTest.php
php artisan test tests/Feature/TteManagementTest.php tests/Feature/WhatsAppWebLinkTest.php
```

Catatan: Sintaks PHP dan JS sudah diperiksa saat membuat paket. Pengujian Laravel sepenuhnya harus dilakukan pada project dengan `vendor/` Composer. Jangan klaim deploy produksi sebelum pengujian berhasil.

## Jika terjadi error

- `no such table: wa_message_templates` → jalankan `php artisan migrate` lalu `php artisan optimize:clear`.
- `404 admin/wa-templates` → cek `routes/web.php` sudah di-merge, lalu `php artisan route:list`.
- `403 Forbidden` saat mengakses preset → login sebagai admin, bukan verifikator.
- Copy Clipboard gagal pada HTTP intranet → gunakan HTTPS; JS menyediakan fallback browser lama tetapi dapat diblokir oleh kebijakan browser.
- Data dummy tidak muncul → periksa `APP_ENV=local`, jalankan seeder kelas secara eksplisit, dan periksa filter status pada UI.
- Tidak bisa buka WhatsApp pada tiket simulasi → memang sengaja diblokir. Gunakan tiket pengujian terkontrol dengan nomor milik Anda jika ingin uji pengiriman nyata.
- **Jangan** menjalankan `php artisan migrate:fresh` untuk patch ini; perintah itu menghapus semua tabel/tiket.

## Keterbatasan

Tidak ada WhatsApp Cloud API, tidak ada bukti delivery pesan, dan tidak ada migrasi NIK atau data pribadi baru. Preset bersifat satu pesan aktif per kombinasi layanan dan tahap. Untuk tambah banyak variasi preset dengan pemilihan manual per tiket, diperlukan pengembangan berikutnya.
