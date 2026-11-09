# TTE v6.1 — Pencegahan Tiket Ganda & Koreksi Email AMS

## Dasar
Target commit `8bcaa8c1af870c824fdcc38c7bed1b59e6b706e3` (main).
Paket merupakan patch kode, **tidak** menjalankan migration ketika file Python dijalankan.

## Langkah setelah memasang file Python
1. Periksa diff: `git diff --check && git diff --stat`.
2. Pastikan `CACHE_STORE` adalah driver lock bersama yang mendukung atomic locks
   (disarankan `database`/`redis`; jangan `array`/`file` untuk multi-server).
3. Backup database produksi; gunakan kunci APP_KEY yang sama dengan data terenkripsi saat ini.
4. Jalankan `php artisan migrate --force` **pada environment yang benar**.
5. Jalankan `php artisan test` (bootstrap test memaksa SQLite :memory:).
6. Validasi UI formulir publik, antrean, detail verifikator, dan pemblokiran webhook lama (HTTP 404).
7. Deploy kode dan migration dalam maintenance window yang sesuai.

## Aturan
- Open/In Progress: permohonan baru ditahan untuk identitas NIK/NIP sama;
  termasuk jenis layanan lain. Tidak dibuat tiket baru dan tidak ada email
  notifikasi *new ticket*. Peringatan publik tidak membuka nomor tiket lama.
- Returned/Done: pengajuan ulang diperbolehkan.
- Upaya pengajuan baru dicatat idempoten dengan submission ID dan tampak bagi
  verifikator (counter + aktivitas); tidak mengubah waktu `updated_at` tiket.
- Integrasi Zoho lama telah dinonaktifkan pada v6.2. Permohonan baru hanya melalui formulir internal.
- Alamat email tiket dapat dikoreksi oleh verifikator **pemilik** tiket aktif,
  hanya setelah pemeriksaan AMS dan pernyataan konfirmasi, dengan alasan wajib.
  Email dibatasi domain `@subang.go.id` dan disimpan dalam histori audit terenkripsi.
- Direktori email **tidak** diubah oleh fungsi ini. Sumber data harus diperbaiki
  melalui prosedur pengelola email.
- Jika notifikasi yang sudah terkonfirmasi masih memuat email lama, konfirmasi
  kirim dibatalkan agar petugas dapat mengirim ulang; histori aktivitas tetap ada.

## Batasan dan risiko yang harus dikendalikan
- Form publik belum memakai autentikasi OTP untuk NIK/NIP. Karena itu, blokir
  publik hanya memberi pesan umum tanpa status, nomor tiket atau PII lain.
- Lock Cache melindungi request serempak dengan identitas kanonik yang sama
  dan shared driver yang sama. Untuk multi-worker gunakan Redis/database
  lock store serta monitor anomali permohonan ganda.
- Identitas historis yang tidak menyimpan NIK/NIP valid tidak dapat dibackfill.
- Migrasi memproses NIK terenkripsi lama melalui Eloquent; pastikan APP_KEY
  benar dan lakukan backup database lebih dahulu.
- Pemberitahuan permohonan aktif disampaikan melalui halaman hasil formulir;
  komunikasi WhatsApp tetap dilakukan oleh petugas.
- File patch menghapus dirinya setelah seluruh file terpasang dan `php -l`
  berhasil (jika PHP tersedia). Backup patch ada di `.tte-patch-backups/`.
