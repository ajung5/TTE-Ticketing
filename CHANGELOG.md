# Catatan Perubahan TTE-Ticketing

Format ini memisahkan **versi rilis aplikasi** dari **identitas perubahan**. Nomor
`VERSION` hanya naik saat sebuah rilis disetujui; perubahan pada branch `main`
tidak dengan sendirinya menjadi rilis production.

## [Unreleased]

### Pengembangan 20 November 2026
- **FIX-20261121-001 — Kirim ulang tautan AMS.** Pengiriman pertama mengikuti Pemeriksaan AMS dan Notifikasi Sesuai AMS. Panel kirim ulang menerima tiga alasan: tautan belum diterima, kedaluwarsa, atau tidak dapat dibuka. Catatan pengiriman pertama yang sudah tersimpan tetap dapat dibaca sebagai arsip.
- **FEAT-20261120-003 — Pengiriman tautan AMS.** Fitur awal mencakup pencatatan pengiriman pertama dan pengiriman ulang tautan aktivasi akun, pembaruan sertifikat, verifikasi nomor WhatsApp, serta reset passphrase. Riwayat lama dan preset reset kustom tetap tersedia.

- **FEAT-20261120-001 — Kirim ulang link Reset Passphrase.** Verifikator
  melakukan pengiriman ulang resmi melalui AMS BSrE, kemudian mencatat
  percobaan per tiket dan menyiapkan notifikasi WhatsApp. Pengiriman
  tidak diklaim sebagai bukti bahwa email diterima.
- **FEAT-20261120-002 — Pengalihan tiket aktif oleh admin.** Admin dapat
  mengalihkan penanggung jawab tiket berstatus Diproses kepada verifikator
  aktif, dengan alasan wajib, audit trail, dan tanpa mengubah nomor tiket.
- **SEC-20261120-001 — Kontrol tindak lanjut.** Otorisasi berbasis kepemilikan,
  pencatatan per percobaan, pembatasan permintaan, serta konfirmasi WA manual.


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

- Versi yang *dikonfigurasi* sebelum standarisasi: `1.0.0` (sebelumnya default
  pada `config/tte.php`, dan bisa ditimpa lewat `.env`).
- **Jangan anggap `1.0.0` sudah dirilis secara resmi hanya karena tertera di
  footer.** Rilis yang sah perlu tag Git, hasil test, dan catatan deployment.
- Label paket kerja sebelumnya (misal `v6.20` / `v6.20.1`) adalah **nomor paket
  implementasi**, bukan nomor aplikasi rilis.

Panduan lengkap: [`docs/VERSIONING.md`](docs/VERSIONING.md).
