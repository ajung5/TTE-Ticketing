# TTE-Ticketing — Test Suite Rebaseline (4 November 2026)

**Baseline GitHub:** `ajung5/TTE-Ticketing`, `main`, commit `ce4f6d1578ddacde363e0297a2143b3778f11f43`.

## Tujuan

Mengganti asumsi pengujian AMS versi lama dengan kontrak perilaku aplikasi terkini **tanpa menghapus riwayat test, tanpa migrasi baru, dan tanpa melemahkan validasi aplikasi**.

Paket ini **hanya mengubah konfigurasi penemuan test** (`phpunit.xml`), menambahkan **lima file Feature Test baru**, dan menyertakan konfigurasi pembanding serta dokumentasi. Controller, model, route, database, dan template produksi **tidak diubah**.

### Yang dijalankan suite utama

1. Seluruh pengujian `tests/Unit/` yang sebelumnya tersedia.
2. Semua `tests/Feature/` yang **tidak** dicantumkan pada daftar karantina di bawah.
3. Lima pengujian domain baru di `tests/Feature/Current/`.
4. `AmsSequentialVerificationV27Test.php` dan `AmsActualProgressV35Test.php` tetap aktif karena sebelumnya telah dikonfirmasi lulus oleh pemilik repository.

### Karantina spesifikasi historis (12 file)

File tetap ada dalam repository, tetapi tidak dieksekusi oleh `phpunit.xml` utama. Mereka **bukan ditandai Passed**. Seluruhnya bisa dijalankan dengan `phpunit.all-including-legacy.xml`.

| File historis | Alasan perlu penulisan ulang |
|---|---|
| `ActivatedPendingIssuanceV25Test.php` | Input tanpa aktivasi/validitas eksplisit |
| `AmsCertificateIssuanceV18Test.php` | Ekspektasi pemeriksaan sebelum aktivasi |
| `AmsCompletionGateV17Test.php` | Status terbit dianggap cukup untuk selesai |
| `AmsDropdownDecisionV16Test.php` | Asumsi aktivasi opsional dan jumlah template tetap |
| `AmsIssuanceCompletionV19Test.php` | Tidak mengirim semua parameter AMS wajib |
| `AmsServiceCorrectionV24Test.php` | Kombinasi asumsi lama; sebagian pengujian masih relevan |
| `InboxReminderWhatsAppTest.php` | Alur notifikasi dan ekspektasi template historis |
| `PublicRequestAndAmsVerificationTest.php` | Campuran pengujian relevan dan asumsi AMS lama |
| `ReturnForCorrectionV21Test.php` | Koreksi layanan dilakukan pada tahap yang kini berubah |
| `TteManagementTest.php` | Campuran pengujian relevan dan satu skenario AMS lama |
| `WaTemplateManagementTest.php` | Ekspektasi hard-coded tepat 10 notifikasi |
| `WhatsAppWebLinkTest.php` | Form layanan diuji dengan input pemeriksaan lama |

**Trade-off:** Karantina per file turut menonaktifkan beberapa test yang sebenarnya masih lulus di dalam file yang sama. Cakupan itu digantikan sebagian oleh domain baru, dan tetap perlu audit coverage setelah menjalankan test di Mac. Ini bukan bukti bahwa semua perilaku yang dahulu diuji sudah terwakili satu-per-satu.

## Matriks keputusan inti

| Aktivasi | WA | Terbit | Status SE | Hasil / pembatasan |
|---|---|---|---|---|
| Belum | N/A | N/A | N/A | Arahkan aktivasi; jangan simpulkan status lanjutan |
| Sudah | Belum | Belum | N/A | Arahan set passphrase; WA juga harus diverifikasi sebelum selesai; tetap satu tiket |
| Sudah | Sudah | Belum | N/A | Arahan set passphrase; tetap satu tiket |
| Sudah | Belum | Sudah | Aktif | Prioritaskan verifikasi WA; selesai ditolak |
| Sudah | Sudah | Sudah | Aktif | Evaluasi layanan, riwayat, dan hasil aktual |
| Sudah | Sudah | Sudah | Expired | Pembaruan atau koreksi jenis layanan; tidak boleh selesai saat expired |
| Sudah | Belum/Sudah | Sudah | Revoke | Penerbitan Ulang; tidak boleh selesai saat revoke |
| Sudah | Sudah | Sudah | Aktif setelah sebelumnya Revoke | Satu tiket; proses aktual Penerbitan Ulang |
| Sudah | Sudah | Sudah | Aktif setelah sebelumnya Belum Terbit | Satu tiket; proses aktual Penerbitan SE |
| Sudah | Sudah | Sudah | Aktif setelah sebelumnya Expired (Pembaruan) | Satu tiket; hasil aktual Pembaruan |
| Sudah | Sudah | Sudah | Aktif, tetapi permohonan salah | Klarifikasi dan rekomendasi; returned hanya jika tidak dapat dilanjutkan |

**Catatan:** 32 kombinasi logis adalah model kombinatorial sederhana dari lima parameter, bukan 32 proses terpisah. Selain itu terdapat kondisi **belum diperiksa** dan **transisi**; pengujian baru memilih skenario bernilai risiko tinggi, bukan mengklaim seluruh 32 kombinasi telah diuji secara eksplisit.

## Struktur pengujian baru

| File | Lingkup |
|---|---|
| `AmsBusinessRulesTest.php` | Validasi wajib, status N/A, Revoke/Expired, prioritas notifikasi, kesesuaian layanan |
| `ServiceTransitionsTest.php` | Nomor tiket stabil, hasil aktual, Done/Returned, kepemilikan, invalidasi koreksi |
| `WhatsAppWorkflowTest.php` | Pemilihan pesan, pembukaan dan konfirmasi manual, pembatalan konfirmasi lama, hasil akhir |
| `AuthorizationAndReportingTest.php` | RBAC, claim/release, monitoring Revoke, CSV, Returned vs Done |
| `NotificationTemplateContractTest.php` | Preset admin, placeholder, masking NIK, WhatsApp Web, kompatibilitas tiket historis |

## Instalasi yang aman di Mac

Lakukan **di branch baru** dan pastikan commit lokal terbaru sama dengan baseline di atas:

```bash
cd /Users/sandi/Dev/TTE-Ticketing
git status --short
git rev-parse HEAD

git switch -c test-rebaseline-2026-11-04
# Salin/ekstrak file ZIP ke root repository, mengikuti struktur folder yang sama.
php artisan optimize:clear
php artisan migrate:status
```

Paket ini **tidak membutuhkan migrasi tambahan**. Jangan menjalankan `migrate:fresh`, `db:wipe`, atau rollback database operasional.

Jalankan berurutan:

```bash
php artisan test tests/Feature/Current --compact
php artisan test tests/Feature/AmsSequentialVerificationV27Test.php --compact
php artisan test tests/Feature/AmsActualProgressV35Test.php --compact
php artisan test --compact
```

Untuk memeriksa kembali seluruh test versi lama, termasuk yang diketahui gagal:

```bash
./vendor/bin/pest -c phpunit.all-including-legacy.xml
```

**Pengamanan:** `phpunit.xml` menetapkan `APP_ENV=testing`, `DB_CONNECTION=sqlite`, dan `DB_DATABASE=:memory:`. `tests/TestCase.php` dari repo saat ini juga melakukan guard sebelum `RefreshDatabase`. Pastikan `APP_KEY` test tersedia dan jalankan `php artisan config:clear` bila konfigurasi cache lama terdeteksi.

## Kriteria terima

1. Semua skenario baru benar-benar lulus di lingkungan lokal; jika gagal, evaluasi **kode aplikasi** sebelum mengubah ekspektasi test.
2. `AmsSequentialVerificationV27Test` dan `AmsActualProgressV35Test` tetap lulus.
3. Semua test suite utama berjalan pada SQLite `:memory:`; tidak menyentuh database pengembangan/produksi.
4. Tidak ada peristiwa `Done` ketika status sertifikat belum terbit, expired, revoked, atau WA belum terverifikasi.
5. `ticket_number` dan `layanan` awal tidak berubah saat proses yang sah berlanjut; `service_outcome` menyatakan hasil aktual.
6. Admin tidak dapat mengambil/menyelesaikan tiket. Verifikator lain tidak dapat mengubah tiket milik petugas lain.
7. Template dan pelaporan tidak memperlakukan jumlah jenis notifikasi sebagai konstanta 10.

## Keterbatasan dan tindak lanjut

- **Pengujian Laravel belum dijalankan oleh penyusun paket** karena lingkungan ini tidak memiliki `vendor/` (dependency Composer) dan ekstensi PDO SQLite. Lolos `php -l` dan validasi XML **bukan** bukti full suite lulus.
- Pengujian CSRF pada middleware **tidak** tervalidasi oleh suite fitur karena `tests/TestCase.php` mengecualikan middleware CSRF saat HTTP test. Perlu pengujian keamanan pada staging dengan middleware produksi aktif.
- Status Aktif di AMS **tidak otomatis** membuktikan tindakan pelayanan tertentu benar-benar dilakukan. Saat ini `Tandai Selesai` diperlakukan sebagai konfirmasi manual verifikator. Perlu prosedur operasional untuk memastikan verifikator tidak menyelesaikan tiket hanya karena status SE aktif.
- `hadCertificateState()` pada controller mencari fragmen teks dalam `ticket_activities.note`. Dalam jangka panjang sebaiknya memakai jejak status terstruktur agar tahan perubahan format catatan.
- Penonaktifan pengujian historis melalui daftar `exclude` adalah tahap transisi, **bukan tujuan akhir**. Setelah baseline utama lulus, migrasikan cakupan unik dari 12 file satu per satu dan hapus pengecualiannya.

## Rollback

Jika terjadi error konfigurasi atau regresi, kembalikan file melalui Git pada branch eksperimen ini:

```bash
git restore phpunit.xml
# Hapus hanya file baru yang disalin dari ZIP (jangan hapus file lama dan jangan ubah data).
```

Konfigurasi `phpunit.all-including-legacy.xml` tersedia agar audit lama tetap bisa dijalankan kapan saja.
