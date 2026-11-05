# TTE-Ticketing v58 — Penyederhanaan workflow admin/verifikator

Basis source: GitHub `ajung5/TTE-Ticketing` commit `a2aea5d` (5 November 2026).

## Perubahan
- Panel **Tindakan Berikutnya** di detail tiket, hanya navigasi ke aksi asli, tanpa bypass backend.
- Pemeriksaan AMS tetap server-authoritative dan kondisional.
- WhatsApp: membuka ulang pesan sama tidak menghapus konfirmasi; pesan berubah harus dikonfirmasi ulang.
- Antrean awal **Milik Saya**, tab **Semua Tiket** eksplisit, antrean baru dari tiket tertua.
- Ringkasan usia antrean operasional di admin (bukan SLA).
- Status direktori, konfigurasi email, queue, failed jobs **read-only** di dashboard admin. Tidak menguji atau mengklaim email terkirim, tidak membuktikan scheduler berjalan.
- Kirim reset password verifikator melalui Manajemen Verifikator; hak akses admin tetap sama.
- Preset WhatsApp menampilkan preview dengan data fiktif lokal, tidak mengambil data pegawai.

## Pemeriksaan pascainstalasi
1. Buka detail tiket tanpa AMS, dengan WA belum dikonfirmasi, dengan WA telah dikonfirmasi, tiket Done, dan tiket Returned.
2. Kirim WhatsApp yang sama dua kali; `confirmed_at` tidak boleh hilang setelah konfirmasi. Ubah isi pesan / data yang dirender, lalu ulangi; konfirmasi harus diminta lagi.
3. Verifikator: awal menampilkan Milik Saya; tab Baru urut tiket tertua terlebih dahulu; tidak melihat tiket milik petugas lain.
4. Dashboard admin: indikator direktori dan SMTP adalah indikasi konfigurasi, bukan health check yang mengirim email atau melakukan sync.
5. Periksa desktop (1280px) dan mobile (390px), keyboard fokus, menu serta notifikasi WA native.

## Batasan
- Tidak menambah migration, seeder, dependensi Composer, akses admin ke pemrosesan tiket, atau aturan selesai AMS.
- UI hanya menggunakan nilai `$serviceEvent` dari `TicketController`, tidak menghitung ulang aturan AMS di client.
- Input/validasi kesesuaian layanan AMS tidak dihapus demi mempertahankan skenario klarifikasi dan ketepatan audit.
- Untuk penggunaan production, backup DB dan storage privat secara terpisah sebelum deploy.
