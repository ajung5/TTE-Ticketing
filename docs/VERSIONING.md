# Kebijakan versi dan rilis TTE-Ticketing

## 1. Tiga identitas yang berbeda

| Identitas | Bentuk | Contoh | Dipakai untuk |
| --- | --- | --- | --- |
| **Versi rilis aplikasi** | `MAJOR.MINOR.PATCH` | `1.0.0`, `1.1.0`, `1.1.1` | Footer, rollback, Git tag `vX.Y.Z` |
| **Perubahan fitur** | `FEAT-YYYYMMDD-NNN` + commit `feat:` | `FEAT-20261112-001` | Melacak penambahan kemampuan; tidak otomatis menaikkan versi saat commit |
| **Perbaikan bug** | `FIX-YYYYMMDD-NNN` + commit `fix:` | `FIX-20261112-001` | Melacak koreksi/regresi; tidak otomatis membuat rilis |

Nomor paket instalasi/patch Python (mis. `v6.20.1`) **bukan salah satu dari ketiganya**.
Label tersebut hanya mengenali paket pekerjaan. Untuk refactor/dokumentasi,
gunakan prefiks commit `refactor:`, `docs:`, `test:`, atau `chore:`.

## 2. Sumber kebenaran versi aplikasi

- `VERSION` di root adalah **satu-satunya sumber nomor aplikasi rilis**.
- `config/tte.php` membaca `VERSION`; footer menampilkan `v` + nilai tersebut.
- Variabel `.env` lama `TTE_APP_VERSION` **tidak lagi dibaca**. Hapus entri
  lama jika ada untuk menghindari kebingungan saat audit konfigurasi.
- `git rev-parse --short HEAD` adalah identitas **build/source**; berbeda
  dari versi rilis, dan perlu dicatat pada setiap deployment.
- Pembaruan `VERSION` belum membuktikan deployment berhasil atau Git tag ada.

Cek versi lokal:

```bash
cat VERSION
php artisan tinker --execute="echo config('tte.version'), PHP_EOL;"
git rev-parse --short HEAD
```

## 3. Aturan Semantic Versioning

| Perubahan | Kenaikan saat rilis disetujui | Contoh dari `1.0.0` |
| --- | --- | --- |
| Perbaikan kompatibel saja | PATCH | `1.0.1` |
| Fitur kompatibel | MINOR | `1.1.0` |
| Perubahan yang memutus kompatibilitas/kontrak | MAJOR | `2.0.0` |

Jika satu rilis menggabungkan fitur dan bug fix, pilih tingkat kenaikan
**tertinggi yang dibutuhkan**. Nomor PATCH kembali menjadi `0` ketika MINOR
naik. Lolos test **bukan satu-satunya syarat** rilis production.

Contoh: sinkronisasi 5 menit adalah `FEAT-20261112-001`. Jika masuk rilis
fitur berikutnya dari baseline `1.0.0`, **kandidat** nomor rilisnya `1.1.0`,
bukan `6.20`/`6.20.1`. Penetapan nomor final dan rollout tetap memerlukan
persetujuan pengelola.

## 4. Prosedur pengembangan dan rilis

1. Perubahan pada branch development/main dicatat di `CHANGELOG.md` bagian
   `[Unreleased]` dengan ID `FEAT-...` / `FIX-...` dan dampaknya.
2. Verifikasi skema/migration, backup, privilege, workflow TTE, keamanan data,
   serta lakukan `php artisan test` dan tes manual skenario prioritas.
3. Untuk rilis yang disetujui, ubah `VERSION` tepat sekali ke versi baru,
   pindahkan isi `[Unreleased]` ke bagian `## [X.Y.Z] - YYYY-MM-DD` di
   `CHANGELOG.md`, dan sisakan `[Unreleased]` untuk perubahan berikutnya.
4. Commit bersama source yang akan dirilis. **Setelah review** dan test,
   buat tag Git annotated pada commit final: `git tag -a vX.Y.Z -m "Release X.Y.Z"`.
   Pastikan nama tag sesuai isi `VERSION`; push tag hanya setelah persetujuan.
5. Deploy **commit/tag terpilih** (bukan otomatis `main`). Jalankan prosedur
   migrasi dan validasi operasional sesuai perubahan, lalu cek footer dan log.
   Catat versi, SHA commit, waktu deployment, operator, hasil tes, dan rollback.
6. Untuk hotfix setelah rilis, gunakan ID `FIX-...`, naikkan PATCH ketika
   merilis, dan buat tag baru. Jangan menimpa tag lama.

**Jangan mengubah `.env` produksi untuk menebak nomor versi.** Jangan
menjalankan `migrate:fresh` pada database produksi. Rollback source harus
mempertimbangkan kompatibilitas migrasi/skema dan data yang telah berubah.

## 5. Kebijakan baseline saat ini

- `VERSION` dimulai dari `1.0.0` agar perilaku footer lama tetap konsisten.
- Ini adalah **metadata konfigurasi awal**, bukan klaim bahwa rilis `v1.0.0`
  sudah dipublikasikan atau di-deploy ke production.
- Riwayat paket `v6.13` hingga `v6.20.1` pada README lama tidak secara
  otomatis menjadi riwayat versi rilis; tetap dapat dipakai sebagai
  referensi implementasi historis.
- Patch versioning **tidak membuat Git tag, GitHub Release, commit, atau push**.
