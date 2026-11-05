# TTE-Ticketing v59 — Empat perbaikan UI (khusus setelah v58)

## Ringkasan
1. **Preset WA:** setiap editor menyajikan shortcut token wajib dan `<details>` Semua Placeholder dan Panduan Penulisan. Sidebar desktop menjadi sticky dengan parent setinggi daftar; pada layar sempit sidebar redundan disembunyikan, panduan inline tetap tersedia. Klik token lokal menyasar textarea milik preset yang sedang diedit.
2. **Mobile:** tombol Kembali ke Atas tampil hanya pada halaman internal, viewport <= 680 px, sudah scroll > 280 px, dan jarak ke bawah <= 180 px. Kontrol aksesibel; smooth scroll mengikuti preferensi reduced motion.
3. **AMS:** label dua opsi penerbitan disederhanakan menjadi **Sudah Terbit** / **Belum Terbit**. Nilai database `issued` / `not_issued` dan validasi backend tetap sama.
4. **AMS:** panel **LAYANAN DIAJUKAN OLEH PEMOHON** tampil langsung sebelum formulir pemeriksaan, sehingga kebutuhan asli pengguna tidak tersamarkan oleh keputusan AMS.

## Basis dan kompatibilitas
GitHub `ajung5/TTE-Ticketing`, `main`, commit `6d4ddcb2ef20bd19dac60bb1fca3ebb47b052b93` (05 November 2026, v58). Installer **hanya menambahkan v59** ke source v58 lengkap. Jika v58 tidak lengkap atau anchor tidak sesuai, installer berhenti tanpa memodifikasi source.

## Instalasi dari root proyek
```bash
python3 patch_tte_v59_incremental.py --check
python3 patch_tte_v59_incremental.py
php artisan optimize:clear
```

`--check` tidak menulis file. Saat apply, installer menyalin semua source yang akan berubah ke backup ZIP di `~/TTE-Ticketing-patch-backups`, menerapkan secara atomik, menjalankan PHP lint, Node lint, Blade cache, tes fokus v59, dan `php artisan test --compact`. Jika gagal, source otomatis dikembalikan. Installer menghapus dirinya sendiri hanya jika semua tes berhasil.

## Pengujian manual
- Login admin, buka preset WhatsApp terakhir, buka panduan dan klik `{nama_pemohon}`; pastikan token tersisip pada editor itu, bukan editor lain.
- Di mobile Chrome/Safari, scroll sampai mendekati akhir halaman panjang, pastikan tombol **Ke Atas** muncul dan kembali ke awal saat diketuk. Tombol tidak tampil di desktop/ketika baru di bagian atas.
- Login verifikator, buka tiket Pembaruan, pastikan **LAYANAN DIAJUKAN** tampil di kartu AMS; pilihan penerbitan hanya **Sudah Terbit/Belum Terbit**.
- Skenario SE aktif, expired, revoke, set passphrase dan no-activation harus tetap mengikuti backend lama; admin tidak boleh memproses tiket.

## Batasan
Paket ini **berisi source perubahan lengkap dalam installer**, dan file baru secara utuh. Bukan archive hasil `git clone` seluruh repository; untuk memperoleh salinan project lengkap gunakan repo GitHub dan terapkan patch tersebut. Tidak mengubah migrasi, seeder, schema, kredensial, dan APP_KEY.
