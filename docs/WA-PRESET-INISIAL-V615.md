# TTE-Ticketing v6.15 — Nama atau Inisial Verifikator di Preset WhatsApp

Baseline: `main` GitHub commit `85719f46866fad9a621935a10740cb4435bf5f75` (10 November 2026).

- Preset WA wajib menyertakan `{nama_pemohon}`, `{nomor_tiket}`, serta **salah satu** dari `{nama_verifikator}` atau `{inisial_verifikator}`.
- Kedua placeholder sudah didukung renderer lama; v6.15 membuka pilihan inisial saja pada validasi editor, menyediakan tombol cepat, serta menjelaskan ketentuannya.
- Inisial verifikator ditentukan bebas oleh **administrator** pada Manajemen Verifikator, termasuk huruf kecil, spasi, angka, titik, garis miring. Tidak lagi dipaksa uppercase. Tetap maksimal **12 karakter** karena kolom `users.initials` memakai `varchar(12)`; control characters (newline, tab) ditolak.
- Jika inisial belum diisi, `{inisial_verifikator}` akan menggunakan nama petugas sebagai fallback, bukan `-`.
- Data tersimpan, preset lama, dan aturan self-claim, pengiriman WA manual, serta RBAC tidak berubah. Tidak menjalankan migrasi atau seeder dan **tidak mengubah isi preset yang sudah disimpan**.
- Pilih placeholder pada admin > Preset WhatsApp; gunakan `Terima kasih, {inisial_verifikator}` dalam pesan jika ingin hanya penanda singkat.

## Instalasi di development

Jalankan di root repository:

```bash
python3 tte_v615_wa_initials_flexible.py --dry-run
python3 tte_v615_wa_initials_flexible.py --run-tests
npm run format
npm run format:check
npm run build
git diff --check
git status --short
```

`--run-tests` akan menjalankan test v6.15 dan kemudian seluruh Laravel feature test. Gagal test akan memicu rollback file yang diubah oleh patch ini. `--apply` menerapkan tanpa menjalankan test. Installer otomatis terhapus setelah instalasi sukses kecuali digunakan `--keep-installer`.

Backups: `.tte-patch-backups/v615-wa-initials-<timestamp>/`.

Periksa manual: buat inisial custom dengan huruf kecil + titik/spasi pada akun verifikator; ubah satu preset agar hanya memakai `{inisial_verifikator}`; simpan; buka tiket milik verifikator tersebut; lihat preview WA tanpa mengirim. Pastikan nomor tiket/nama pemohon tetap benar dan notifikasi tetap melalui konfirmasi manual.
