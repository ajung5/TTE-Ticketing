# TTE-Ticketing v7.2 — Nama Pengisi Survei dan Tabel Admin

Acuan repository: ajung5/TTE-Ticketing main, commit b69f9835876aaf3ed760f3ae0475ea130b589923.

## Cara memasang
1. Backup proyek lokal, khususnya file yang mungkin sudah diedit.
2. Salin folder `app`, `database`, `resources`, dan `tests` ke root proyek Laravel (merge folder, replace hanya file dengan nama sama). Jangan menghapus folder proyek.
3. Jalankan dari direktori proyek:

```bash
php artisan optimize:clear
php artisan migrate
php artisan test tests/Feature/PublicSurveyTest.php
php artisan serve
```

## Perubahan
- Formulir publik `/survei/{ticket}` meminta **Nama Pengisi Survei** (wajib), terpisah dari nama pemohon pada tiket.
- Nama disimpan di `service_survey_responses.respondent_name`.
- Admin `/admin/survei`, tabel **Masukan Pemohon**, menampilkan tepat urutan: Waktu, Nomor Tiket, Nama Pengisi Survei, Nilai, Saran / Masukan.
- Nilai di tabel adalah overall_rating (skala 1–5), seperti sebelumnya.
- Respons lama tetap ada; namanya ditampilkan `Belum dicatat` karena memang belum pernah dikumpulkan.
- Hanya admin bisa melihat hasil sesuai otorisasi existing.

## Batasan
- Nama diisi sendiri oleh responden dan bukan bukti verifikasi identitas.
- Mengubah file tidak otomatis memperbarui GitHub; patch ini hanya untuk penyalinan manual.
