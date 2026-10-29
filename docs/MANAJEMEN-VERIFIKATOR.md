# Manajemen Verifikator — TTE-Ticketing

Fitur tambahan berdasarkan repository `ajung5/TTE-Ticketing`, commit acuan `fed92984306929de214401bf123d2f31d676249d`.

## Akses

- Hanya pengguna `role=admin` yang dapat mengakses `/admin/verifiers` dan aksi CRUD.
- Verifikator hanya dapat mengelola tiket yang berwenang, bukan akun petugas lain.
- Admin tidak memperoleh hak mengambil/menyelesaikan tiket.

## Fitur

1. Tambah verifikator: nama, email unik, inisial opsional, password awal + konfirmasi.
2. Edit profil: nama, email, dan inisial.
3. Ganti password langsung dari halaman admin tanpa mengetahui atau menampilkan password lama. Minimal 12 karakter, kombinasi huruf besar, huruf kecil, angka, simbol. Password disimpan dengan hash Laravel.
4. Hapus akun: **soft delete** (nonaktif login, hilang dari daftar aktif), bukan menghapus histori database.
5. Pencegahan penghapusan jika verifikator masih menangani tiket berstatus `In Progress`.
6. Riwayat petugas tetap terlihat pada tiket selesai dan aktivitas melalui relasi `withTrashed()`.
7. Semua aksi perubahan akun dicatat di `audit_logs`.
8. Jika email petugas yang sudah dihapus didaftarkan kembali, identitas pengguna lama diaktifkan kembali (id historis tetap sama).

## Instalasi

Salin semua file dari ZIP sesuai struktur direktori Laravel. Jangan mengganti file `.env` atau kredensial.

```bash
php artisan optimize:clear
php artisan migrate
php artisan test tests/Feature/AdminVerifierManagementTest.php
php artisan test
php artisan serve
```

Buka `/admin/verifiers` sebagai admin. Migrasi `2026_10_30_000000_add_soft_deletes_to_users.php` menambahkan `users.deleted_at`.

**Catatan keamanan:** Jika menggunakan `SESSION_DRIVER=database`, pergantian password/penghapusan akun juga menghapus sesi database milik verifikator. Untuk session driver lain, siapkan mekanisme pencabutan sesi tambahan bila dibutuhkan.

## Bagian yang dapat ditambahkan ke README.md

```markdown
### Manajemen Verifikator (Admin)

Admin dapat menambahkan akun verifikator, mengedit profil (nama, email, inisial),
mengganti password, dan menghapus akun melalui `/admin/verifiers`.
Penghapusan menggunakan soft delete agar jejak audit serta riwayat tiket tetap ada.
Akun dengan tiket `In Progress` tidak dapat dihapus sebelum penanganannya selesai.
Seluruh perubahan akun tercatat di `audit_logs`. Verifikator tidak memiliki akses
ke halaman ini, dan admin tetap tidak dapat memproses tiket operasional.
```
