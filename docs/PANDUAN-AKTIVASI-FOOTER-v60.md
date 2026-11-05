# TTE-Ticketing v60 — Aktivasi → SE Aktif → Selesai, Footer Global

Basis repository: `ajung5/TTE-Ticketing` commit `ff134322e75e164c8205721f52b88bc6fdbd233d` (v59).

## Perubahan proses bisnis

- Untuk **Reset Passphrase** atau **Pembaruan**, jika tiket memiliki riwayat pemeriksaan akun **belum aktivasi**, kemudian hasil pemeriksaan ulang mencatat akun **aktif**, WA **terverifikasi**, sertifikat **sudah terbit** dan **aktif**, sistem langsung menghasilkan `ready_for_completion`.
- Verifikator cukup menekan **Tandai Selesai**, kemudian membuka dan mengonfirmasi notifikasi **Selesai + Survei**.
- Nomor tiket tidak berubah; tidak ada pengajuan ulang; notifikasi Reset/Pembaruan antara tidak diwajibkan lagi untuk jalur ini.
- Riwayat pemeriksaan awal tetap harus nyata, bukan nilai yang disimpulkan otomatis.
- SE belum terbit / expired / revoked atau WA belum terverifikasi tetap tidak bisa diselesaikan.
- Perubahan status tetap bersumber dari pemeriksaan manual AMS, bukan sinkronisasi langsung AMS.
- Hasil akhir mengikuti indikator SE aktif setelah proses aktivasi. Kode tidak merekam tanggal penerbitan sertifikat, hanya status terverifikasi manual.

## Footer

Footer di pojok kanan bawah area konten untuk administrator, verifikator, dan halaman publik (termasuk login/survei). Format: `Copyright@DiskominfoSubang 2026-TAHUN_BERJALAN vX.X.X`.

Tahun akhir dihitung dengan `now('Asia/Jakarta')->year`. Versi dari `config('tte.version')`, diset melalui `.env`:

```env
TTE_APP_VERSION=1.0.0
```

Tanpa env, fallback `1.0.0`. Versi tersebut nilai default awal untuk ditetapkan pengelola, **bukan** bukti release semantik. Naikkan sesuai kebijakan release internal, misalnya `TTE_APP_VERSION=1.0.1`.

## Instalasi

1. Salin `patch_tte_activation_footer_v60.py` ke root Laravel (sejajar `artisan`).
2. `python3 patch_tte_activation_footer_v60.py --check`
3. `python3 patch_tte_activation_footer_v60.py`
4. `php artisan optimize:clear` sesudah sukses. Di production, jalankan saat jendela pemeliharaan.

Installer membuat backup, memakai atomic writes, menolak bila baseline berubah, dan mengembalikan source jika tes gagal. Self-delete dilakukan hanya setelah seluruh tes lulus. Kode produksi hanya berubah pada file controller, layout, config, stylesheet/partial footer dan test baru. Tidak ada migrasi DB atau pengubahan credential.

## Pengujian manual penting

- Reset belum aktivasi → pesan aktivasi → aktivasi, WA verified, SE active → Tandai Selesai → Selesai + Survei.
- Pembaruan belum aktivasi → alur identik.
- Reset/Pembaruan tanpa riwayat aktivasi tetap menunjukkan notifikasi layanan sesuai aturan lama.
- Sertifikat expired/revoked atau WA belum verified: tombol Tandai Selesai masih nonaktif.
- Footer: cek admin, verifikator, formulir publik pada desktop dan handphone; pastikan posisi kanan bawah tidak tertutup tombol Ke Atas.

> Installer mengharuskan tes menggunakan database SQLite `:memory:` sesuai `phpunit.xml`. Jika `vendor/` belum terpasang, jalankan `composer install` sebelum instalasi.
