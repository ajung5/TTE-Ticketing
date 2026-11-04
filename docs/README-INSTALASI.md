# Baseline Instalasi TTE-Ticketing — 2026-11-04 (v38)

Basis sumber: `ajung5/TTE-Ticketing`, branch `main`, commit `6a94b7391f553e36cf203070633be253b86ab12b`.

## Desain skema dan bootstrap

Migration baru hanya berisi **skema operasional**, bukan contoh tiket atau pesan WhatsApp yang menimpa kustomisasi. Total **8 migration**: 3 bawaan Laravel (users, cache, jobs), 4 skema domain (tiket dan audit; template WA; direktori email; survei), dan 1 kompatibilitas ukuran `wa_message_templates.event_type` untuk deployment existing MySQL/PostgreSQL. Sebanyak 13 migration tambah-kolom/preset historis dihapus dari source; file yang tersisa memakai nama migration lama agar database existing tidak membuat ulang tabel.

Seeder operasional:
- `DatabaseSeeder` → `TteBaselineSeeder` → **akun admin/verifikator** (berdasarkan variabel environment; tidak ada password default) dan `WaTemplatePresetsSeeder` (11 preset aktif sesuai `WaMessageTemplate::TEMPLATE_SERVICE_CODES`).
- Seeder preset menggunakan `insertOrIgnore`; **tidak mengubah template yang telah diedit admin**.
- **Tidak ada tiket, survei, pegawai direktori, atau data dummy** yang dibuat pada seeding standar. `DemoTicketsSeeder.php` sengaja dikeluarkan dari source.
- Direktori email harus diimpor secara terpisah sebelum formulir publik dapat memvalidasi NIK/NIP. Seeder kosong bukan masalah pada instalasi.

## Variabel environment untuk akun awal

Di `.env`, isi alamat email yang sah dan password kuat, minimal 12 karakter:

```dotenv
TTE_BASELINE_ADMIN_EMAIL=admin@example.org
TTE_BASELINE_ADMIN_PASSWORD=GANTI_DENGAN_RAHASIA_MINIMAL_12_KARAKTER
TTE_BASELINE_ADMIN_NAME=Administrator
TTE_BASELINE_ADMIN_INITIALS=ADM

TTE_BASELINE_VERIFIER1_EMAIL=verifikator@example.org
TTE_BASELINE_VERIFIER1_PASSWORD=GANTI_DENGAN_RAHASIA_LAIN_MINIMAL_12_KARAKTER
TTE_BASELINE_VERIFIER1_NAME=Verifikator 1
TTE_BASELINE_VERIFIER1_INITIALS=V1
```

Akun verifikator kedua **opsional** menggunakan `TTE_BASELINE_VERIFIER2_*`. Nilai di atas contoh, jangan gunakan password contoh dalam sistem nyata. Jika akun admin dan verifikator sudah ada, seeder tidak mengganti password atau profilnya. Jika akun belum ada dan variabel tidak lengkap, seeder sengaja gagal daripada membuat akun dengan password lemah.

## Instalasi BARU (database kosong)

1. Konfigurasikan `.env`, database, `APP_KEY` dan kredensial seeder, lalu backup source sebelum patch.
2. Jalankan patch Python dari root repository (patch membuat backup ZIP di luar repository).
3. `composer install --no-dev --optimize-autoloader` (produksi), lalu `php artisan optimize:clear`.
4. `php artisan migrate --force`.
5. `php artisan db:seed --class=DatabaseSeeder --force`.
6. Cek `php artisan migrate:status`, login admin/verifikator, lalu impor direktori email jika akan mengaktifkan formulir publik.

Jangan jalankan `db:seed` sebelum variabel akun awal tersedia, dan jangan isi `APP_DEBUG=true` di produksi.

## Deployment ke database EXISTING

- **Backup database terlebih dahulu.** Patch hanya mengubah source dan **tidak menjalankan SQL**, tidak menghapus isi database.
- Setelah patch, migration yang nama filenya sudah tercatat di tabel `migrations` tetap dianggap selesai Laravel; migration tambahan kompatibilitas ukuran event WhatsApp akan dijalankan ketika `php artisan migrate --force` berikutnya.
- Data tiket, survei, akun, direktori, template kustom, dan riwayat tidak dihapus.
- **Dilarang menggunakan `migrate:fresh`, `db:wipe`, `migrate:reset` atau rollback historis pada database existing** setelah menggabungkan migration; nama dan struktur migration historis sudah diubah/hilang. Jika harus mengembalikan versi source, pulihkan migration lama melalui backup patch/Git bersama backup DB sesuai prosedur.
- Mengubah file migration lama **tidak** memperbaiki skema existing yang sudah berbeda. Migration kompatibilitas hanya menangani ukuran kolom event WhatsApp; perbedaan lain pada DB lama perlu audit skema sebelum deployment.

## Pengujian

Setelah `php artisan optimize:clear`:

```bash
php artisan test --compact
php artisan test tests/Feature/Current/InstallSchemaAndSeedTest.php --compact
```

`tests/TestCase.php` menyiapkan preset WhatsApp melalui seeder khusus **hanya pada database test terisolasi**. Ini menggantikan ketergantungan test terhadap side effect seeding pada migration lama. Pengamanan `sqlite :memory:` dan pembatasan test terhadap database operasional tetap dipertahankan.

12 test Feature legacy yang sebelumnya `exclude` dari `phpunit.xml` dihapus dari source; pengujian aktif, termasuk test AMS V27 dan V35 yang lulus, tetap dipertahankan. Test dummy `tests/Unit/ExampleTest.php` juga dihapus. Riwayat file dapat ditinjau lewat Git.

## Verifikasi patch dan pemulihan

`python3 patch_tte_install_baseline_v38.py --check` hanya mengaudit. `python3 patch_tte_install_baseline_v38.py` menerapkan patch dan **menghapus script itu sendiri hanya setelah sukses**. Patch membuat ZIP backup file asli di direktori home (`~/TTE-Ticketing-patch-backup-*.zip`). Jika proses gagal, script berupaya memulihkan semua file dan **tidak menghapus dirinya sendiri**.
