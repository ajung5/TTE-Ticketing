# Fresh install database TTE-Ticketing (v6.4)

Acuan audit kode: branch `main`, commit `4423a54014d5c49aa8d30c08c95afbba825e6be5` (9 November 2026).

## Prinsip dan keputusan audit

- Ada **9 migration aktif**: 3 Laravel bawaan, 4 domain inti TTE, 1 kompatibilitas ukuran event WhatsApp, dan 1 migrasi v6.1 untuk pencegahan tiket ganda serta koreksi email AMS. **Tidak menghapus file migration historis** karena database existing mungkin telah mencatat nama migration tersebut.
- Migrasi skema inti tiket menggunakan default `source = direct`. `external_submission_id` **tetap dipakai** oleh formulir internal sebagai kunci idempotensi walaupun Zoho telah dihapus.
- Fresh install **tidak membuat tabel `webhook_events`**. Untuk database existing, tabel historis tetap dibiarkan jika sudah tersedia; patch tidak menghapus audit atau data lama.
- Migration kompatibilitas `ensure_wa_event_type_capacity` dipertahankan untuk database MySQL/PostgreSQL lama. Pada fresh install tabel preset sudah menggunakan `VARCHAR(40)`.
- Migration v6.1 tetap membuat `blocked_ticket_attempts` dan `ticket_email_changes` beserta kolom fingerprint identitas, pelacakan duplikat, dan koreksi email. Migrasi ini melakukan backfill hanya pada tiket existing, sehingga aman untuk database baru yang kosong.
- Ketiga seeder masih digunakan. `DatabaseSeeder` menjalankan `TteBaselineSeeder`, yang memprovisikan **satu admin awal dan satu verifikator awal secara interaktif**, serta `WaTemplatePresetsSeeder` (11 template notifikasi). Seeder dapat dijalankan ulang; template kustom dan password existing tidak ditimpa. Tidak ada tiket, survei, maupun pegawai sintetis yang dibuat.

## Fresh install: hanya DATABASE BARU KOSONG (bukan production existing)

1. Buat `.env` untuk lingkungan yang benar, set koneksi database, `APP_KEY`, dan konfigurasi email. Pastikan database **benar-benar baru dan kosong**.
2. Install dependency sesuai `composer.lock`, lalu cek `php artisan about`.
3. Jalankan `php artisan config:clear` dan **`php artisan migrate --force`**. Perintah ini *tidak menghapus tabel existing*; jangan menjalankan `migrate:fresh` / `db:wipe` pada production berisi data.
4. Buka terminal interaktif yang aman (SSH lokal atau server) lalu jalankan **`php artisan db:seed --class=DatabaseSeeder --force`**. Input email, nama, inisial, password admin dan verifikator. Password tidak disimpan dalam source atau `.env`; jangan menggunakan `--no-interaction`.
5. Sinkronkan direktori pegawai menggunakan `php artisan tte:sync-email-directory` setelah kredensial Google Sheets siap. Tiket, survei, dan direktori awal kosong sebelum sinkronisasi.
6. Verifikasi `php artisan migrate:status`, jumlah akun, 11 template WhatsApp, dan alur permohonan internal.

**Jangan jalankan `php artisan migrate:fresh --seed` pada instalasi existing.** Pada fresh install pun lebih aman memisahkan migrasi dan provisioning akun agar input password dilakukan secara interaktif.

## Uji skema dan seeder (hanya lingkungan developer/CI)

`tests/TestCase.php` mengunci database test ke SQLite `:memory:`. Pastikan `vendor/autoload.php` ada, ekstensi `pdo_sqlite` aktif, dan tidak ada config cache produksi.

```bash
php artisan config:clear
php artisan test tests/Feature/Current/InstallSchemaAndSeedTest.php tests/Feature/TteFreshInstallV64Test.php tests/Feature/TteBaselineSeederTest.php
php artisan test
```

Uji manual instalasi baru boleh dilakukan hanya pada database disposable terpisah. Jangan menjalankan test/provisioning ini pada database production.

## Yang sengaja tidak dihapus

| Elemen | Alasan |
| --- | --- |
| Migration `2026_11_04_010000_ensure_wa_event_type_capacity.php` | Kompatibilitas database lama dengan ukuran kolom kurang dari 40 |
| Migration `2026_11_09_090000_add_active_ticket_guard_and_ams_email.php` | Skema deduplikasi, audit email AMS, dan backfill data existing |
| `tickets.external_submission_id` | Token idempotensi formulir internal (`direct-<UUID>`), bukan khusus Zoho |
| Tabel `audit_logs` | Audit operasional aplikasi |
| Seeder `TteBaselineSeeder` | Provisioning dua role awal tanpa kredensial hard-coded |
| Seeder `WaTemplatePresetsSeeder` | 11 template layanan dan notifikasi wajib |

**Catatan rollout:** modifikasi baseline migration hanya mengubah schema untuk database yang belum pernah menjalankannya. Database lama tidak otomatis berubah default sumbernya, dan mungkin masih menyimpan `webhook_events`. Perbaikan default pada database existing harus dievaluasi melalui migration tambahan tersendiri apabila diperlukan, bukan dengan `migrate:fresh`.
