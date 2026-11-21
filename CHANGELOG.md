# Catatan Perubahan TTE-Ticketing

Format ini memisahkan **versi rilis aplikasi** dari **identitas perubahan**. Nomor
`VERSION` hanya naik saat sebuah rilis disetujui; perubahan pada branch `main`
tidak dengan sendirinya menjadi rilis production.

## [Unreleased]

## [1.4.0] - 2026-11-21

### Fitur

- **FEAT-20261121-001 — Tabel pengalihan tiket aktif.** Admin dapat memilih **Alihkan** atau **Salin nomor** pada setiap baris. Daftar mempertahankan pencarian, filter verifikator dan halaman yang masih tersedia setelah pengalihan. (`66d0d1d`)
- **FEAT-20261121-002 — Salin data pemohon.** Verifikator dapat menyalin email dinas yang digunakan pada tiket dan nomor WhatsApp. Ikon salin NIK lengkap hanya tampil bagi pemegang tiket. Pada layar hingga 860 piksel, kartu informasi pemohon tampil sebelum tindakan dan ringkasan AMS. (`5ca710b`)

### Perbaikan

- **FIX-20261121-004 — Navigasi.** Laporan Tiket dan Hasil Survei memiliki breadcrumb menuju dashboard admin. Tautan kembali ke dashboard, antrean dan login menggunakan gaya tombol aplikasi. (`fee2484`)
- **FIX-20261121-005 — Keluar Sistem di HP.** Nama akun mendapat baris sendiri sehingga tombol profil dan keluar tetap terjangkau. Pada layar mendatar yang pendek, menu dapat digulir hingga tombol keluar. (`e357ba9`)

## [1.3.0] - 2026-11-21

### Perbaikan 21 November 2026

- Menghapus sembilan salinan sumber dari cadangan patch AMS v16 dan memperbarui panduan upgrade mengenai preset WhatsApp pada database production.

- **FIX-20261121-003 — Navigasi dan performa halaman.** Tombol Ke Atas tersedia setelah guliran pertama di ponsel. Formulir memakai logo WebP, halaman memuat CSS khusus melalui head, dan editor preset menampilkan daftar placeholder lengkap saat dibuka. Dashboard dan antrean juga membutuhkan lebih sedikit kueri untuk menghitung status tiket.

- **FIX-20261121-002 — Kanal tautan kirim ulang.** Preset verifikasi nomor WA mengarahkan pemohon ke chat WhatsApp dari BSrE. Preset aktivasi dan pembaruan menyebut email dinas, seperti preset reset passphrase. Seeder memperbarui teks bawaan lama dan mempertahankan suntingan admin.

### Pengiriman tautan AMS

- **FIX-20261121-001 — Kirim ulang tautan AMS.** Pengiriman pertama mengikuti Pemeriksaan AMS dan Notifikasi Sesuai AMS. Panel kirim ulang menerima tiga alasan: tautan belum diterima, kedaluwarsa, atau tidak dapat dibuka. Catatan pengiriman pertama yang sudah tersimpan tetap dapat dibaca sebagai arsip.
- **FEAT-20261120-003 — Pengiriman tautan AMS.** Fitur awal mencakup pencatatan pengiriman pertama dan pengiriman ulang tautan aktivasi akun, pembaruan sertifikat, verifikasi nomor WhatsApp, serta reset passphrase. Riwayat lama dan preset reset kustom tetap tersedia.

## [1.2.0] - 2026-11-20

### Pengembangan layanan

- **FEAT-20261120-001 — Kirim ulang link Reset Passphrase.** Verifikator
  melakukan pengiriman ulang resmi melalui AMS BSrE, kemudian mencatat
  percobaan per tiket dan menyiapkan notifikasi WhatsApp. Pengiriman
  tidak diklaim sebagai bukti bahwa email diterima.
- **FEAT-20261120-002 — Pengalihan tiket aktif oleh admin.** Admin dapat
  mengalihkan penanggung jawab tiket berstatus Diproses kepada verifikator
  aktif, dengan alasan wajib, audit trail, dan tanpa mengubah nomor tiket.
- **SEC-20261120-001 — Kontrol tindak lanjut.** Otorisasi berbasis kepemilikan,
  pencatatan per percobaan, pembatasan permintaan, serta konfirmasi WA manual.

## [1.1.0] - 2026-11-12

### Fitur (feat)
- **FEAT-20261112-001 — Sinkronisasi direktori email inkremental.** Pembacaan
  Google Sheets setiap 5 menit; record yang identik tidak ditulis ulang;
  checkpoint keberhasilan dan proteksi penghapusan massal.

### Perbaikan (fix)
- **FIX-20261112-001 — Data uji rollback sinkronisasi.** Memakai alamat email
  valid agar test benar-benar mencapai pemeriksaan penghapusan massal.

### Pemeliharaan (chore)
- **CHORE-20261112-001 — Standarisasi versioning.** File `VERSION` sebagai satu
  sumber versi rilis, pembeda ID fitur/perbaikan, dan prosedur rilis formal.

## Informasi baseline

Tanggal v1.1.0 hingga v1.3.0 mengikuti commit yang memperbarui `VERSION`. Riwayat deployment dicatat terpisah.

- Versi yang *dikonfigurasi* sebelum standarisasi: `1.0.0` (sebelumnya default
  pada `config/tte.php`, dan bisa ditimpa lewat `.env`).
- **Jangan anggap `1.0.0` sudah dirilis secara resmi hanya karena tertera di
  footer.** Rilis yang sah perlu tag Git, hasil test, dan catatan deployment.
- Label paket kerja sebelumnya (misal `v6.20` / `v6.20.1`) adalah **nomor paket
  implementasi**, bukan nomor aplikasi rilis.

Panduan lengkap: [`docs/VERSIONING.md`](docs/VERSIONING.md).
