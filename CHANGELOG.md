# Catatan Perubahan TTE-Ticketing

Format ini memisahkan **versi rilis aplikasi** dari **identitas perubahan**. Nomor
`VERSION` hanya naik saat sebuah rilis disetujui; perubahan pada branch `main`
tidak dengan sendirinya menjadi rilis production.

## [Unreleased]

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
