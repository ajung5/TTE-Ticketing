# TTE-Ticketing v6.13 — Paket UI/UX Lengkap

Baseline diperiksa: `main` commit `641f3b0f78f0801416ef9d70123c8015ed595613` (10 November 2026).

## Cakupan

- **Verifikator**: filter *Perlu Tindakan* (antrean baru + tiket sendiri yang siap diproses), filter *Menunggu Pemohon* (hanya tiket sendiri), ringkasan AMS pada detail tiket.
- **Administrator**: ringkasan berdasarkan *bulan berjalan*, *7 hari kalender*, atau *seluruh periode*, lengkap dengan pembedaan metrik periode dan snapshot antrean. Statistik lama tetap ada dan jelas berlabel akumulasi.
- **Pemohon**: tombol bantuan ketika pencarian NIK/NIP tidak menemukan data, penguncian fokus di modal dan pengembalian fokus, serta fitur salin nomor tiket pada halaman sukses.
- **UI**: tambahan stylesheet scoped untuk desktop/mobile dan regression test.

Tidak mengubah migration, tabel, seeder, kredensial, nomor tiket, aturan self-claim, proses AMS/WhatsApp, atau pembatasan akses.

## Cara memasang

Ekstrak zip, salin `tte_v613_uiux_complete.py` ke root repo TTE-Ticketing pada **development**:

```bash
python3 tte_v613_uiux_complete.py --dry-run
python3 tte_v613_uiux_complete.py --run-tests
npm run format
npm run format:check
npm run build
git diff --check
git status --short
```

`--run-tests` memasang patch, lalu menjalankan regression test baru dan seluruh suite Laravel. Gagal test -> rollback source otomatis. Jika hanya ingin memasang tanpa test otomatis, pakai `--apply`. File patch menghapus dirinya sendiri setelah pemasangan sukses. Tambahkan `--keep-installer` jika ingin menyimpannya. File aslinya tetap tersedia di dalam arsip ZIP.

Cadangan ada di `.tte-patch-backups/v613-*` (pastikan folder ini masuk `.gitignore`).

## Uji penerimaan manual

1. Login verifikator; cek `focus=action` menampilkan tiket `Open` dan `In Progress` milik sendiri yang tidak menunggu pemohon. `focus=waiting` hanya menampilkan tiket sendiri yang sedang menunggu pemohon. Pastikan tiket verifikator lain tidak muncul.
2. Buka detail tiket; periksa ringkasan AMS dan tampilan pada layar 375px / desktop; pastikan aksi WhatsApp, self-claim dan penyelesaian tetap berjalan.
3. Login admin; ganti pilihan periode. Metrik *Masuk* menggunakan `created_at`, *Selesai* menggunakan `completed_at`; snapshot menunggu pemohon dan antrean lama tidak mengikuti filter periode.
4. Form publik: masukkan NIK/NIP yang tidak ada, pastikan bantuan muncul; uji Tab/Shift+Tab/Escape dan pengembalian fokus. Periksa rate-limit dan kondisi direktori tidak tersedia.
5. Pengajuan berhasil: tombol Salin Nomor Tiket muncul hanya saat tiket baru berhasil dibuat. Jika permohonan ditahan karena duplikat, tidak ada tombol salin dan tidak menampilkan tiket lain.
6. Pastikan tampilan responsif, interaksi keyboard, dan stylesheet baru termuat pada production. Aplikasi memakai aset `public/` langsung, jadi `npm run build` bukan pengganti copy aset ke server.

## Keamanan

Lookup NIK/NIP saat ini masih mengembalikan profil lengkap berdasarkan identitas tanpa verifikasi OTP. Ini merupakan risiko enumerasi data pribadi yang **tidak diselesaikan oleh patch UI/UX**. Penanganan terpisah diperlukan sebelum mengklaim formulir publik sudah aman sepenuhnya.

Installer memakai preflight, syntax check, staged changes, backup dan rollback lokal. Tidak menjalankan migration, seeder, atau composer setup. Untuk deployment ke production lakukan proses backup, review diff, dan pengujian terpisah.

## Batasan

Penilaian ini berdasarkan source, bukan screenshot lintas browser. Formatter proyek dapat memformat source yang baru, tetapi pengecekan pixel-perfect/visual dan pengujian WCAG lengkap harus dilakukan di browser sebenarnya.
