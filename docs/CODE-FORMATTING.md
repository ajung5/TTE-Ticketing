# Standar formatting source TTE-Ticketing

Acuan audit repository: `main` commit `9676809329683ac9fcede225983e524fcba0d13d` (10 November 2026).

## Style

- 4 spasi, tanpa tab; line width 100; LF; single-quote bila sintaks mengizinkan.
- PHP murni: Prettier + `@prettier/plugin-php` (`phpVersion: 8.3`, `braceStyle: 1tbs`).
- Blade: `prettier-plugin-blade` parser `blade`, `bladePhpFormatting: safe`.
- JS/CSS: Prettier. File CSS yang sebelumnya satu baris akan dipecah menjadi mudah dibaca.
- SQL: `sql-formatter`, dialek default SQLite, keyword `UPPERCASE`. Untuk SQL MySQL/PostgreSQL, gunakan flag `--sql-dialect=mysql` / `--sql-dialect=postgresql`.
- Query dalam migration PHP tidak diperlakukan sebagai SQL standalone. Jangan menjalankan dua formatter (Pint lalu Prettier) secara bergantian pada PHP.

## Penggunaan — developer, bukan di server production

```sh
npm install --ignore-scripts --no-audit --no-fund
npm run format:check
npm run format
npm run format:check
php artisan test
npm run build
```

`npm install` juga menyinkronkan `package-lock.json` setelah paket `sql-formatter` ditambahkan. Commit `package.json`, `package-lock.json`, dan file source yang memang sudah diformat dalam commit terpisah dari perubahan proses bisnis.

Untuk satu bahasa:

```sh
npm run format:php
npm run format:blade
npm run format:js
npm run format:css
npm run format:sql
npm run format:sql -- --sql-dialect=postgresql
```

Format script memakai mode `--check` secara default. **Tidak mengubah source** sampai Anda menjalankan `--write` atau `npm run format`. Seluruh berkas terlebih dahulu diparse; bila satu saja gagal, tidak ada perubahan. Saat `--write`, setiap berkas yang berubah dibackup ke `.format-backups/`. Backup ini bukan pengganti commit/backup Git dan tidak boleh masuk repository.

Jika `npm run format:check` mengembalikan kode 1, artinya masih ada file yang butuh formatting (bukan error aplikasi). Kode 2 menandakan masalah parser/config. Jika ada kegagalan test setelah formatting, periksa test yang membandingkan substring source secara literal karena formatting dapat mengubah whitespace tanpa mengubah logika.

> Perubahan ini hanya konfigurasi dan kode sumber. Tidak menjalankan `migrate`, `db:seed`, `key:generate`, atau perubahan database.

## Inline HTML pada Blade (v6.8)

- Elemen inline sederhana seperti `<strong>`, `<em>`, `<span>`, dan `<small>` harus mempertahankan opening tag pada satu baris, termasuk bila isi memakai `{{ ... }}`.
- `bracketSameLine: true` diterapkan **hanya pada parser Blade**, bukan pada PHP/JS/CSS. Atribut panjang tetap boleh dipecah ke beberapa baris.
- Formatter juga merapikan kasus `<strong` + newline + `>` **tanpa atribut**, setelah parsing Prettier. Komentar, konten `<script>`, `<style>`, dan `<textarea>` dilewati.
- Jalankan `npm run format:blade` lalu `npm run format:check` dan `php artisan test`. Periksa `git diff` sebelum commit.
