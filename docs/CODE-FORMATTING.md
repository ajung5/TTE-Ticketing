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

## Perbaikan v6.9: konsistensi editor vs CLI

- **`npm run build` menjalankan Vite bundling, BUKAN formatting.** Perintah sumber
  formatting ialah `npm run format`, sedangkan `npm run format:check` hanya memeriksa.
- `tests/.prettierrc.json` yang sebelumnya menduplikasi konfigurasi dihapus agar
  pengaturan file test mengikuti `.prettierrc.json` root.
- VS Code: pasang ekstensi **Run on Save** (`emeraldwalk.runonsave`) dari Marketplace,
  aktifkan ekstensi pada workspace yang Anda percayai. Pengaturan bersama tersedia
  di `.vscode/settings.json`. `editor.formatOnSave` dinonaktifkan untuk PHP, Blade,
  JavaScript, CSS, dan SQL agar tidak ada dua formatter yang saling menimpa.
- Ekstensi akan menjalankan **formatter proyek yang sama** dengan CLI, dengan
  `--file` untuk hanya file yang disimpan; termasuk normalisasi opening tag Blade
  seperti `<strong>` dari v6.8.
- Setelah memasang ekstensi, restart/reload jendela VS Code. File baru yang baru
  pertama kali dibuat mungkin memerlukan Save kedua agar Run on Save terpanggil.
- CLI satu file (gunakan path relatif, dari root repo):

```sh
node scripts/format-source.mjs --check --file resources/views/tickets/index.blade.php
node scripts/format-source.mjs --write --file resources/views/tickets/index.blade.php
npm run format:check
php artisan test
npm run build
```

**Jangan memakai `prettier --write` langsung pada Blade** bila ingin hasil identik
karena formatter proyek mempunyai tahap normalisasi inline-tag setelah Prettier.
Jangan menjalankan `npm run format` di server production. Periksa `git diff`
setelah format massal; hanya commit source yang sudah diuji.

## TTE v6.10 — Penutupan tag HTML Blade

Formatter proyek sekarang menormalisasi pemisahan `>` pada **tag penutup HTML**,
misalnya `</span\n >` menjadi `</span>`. Tag pembuka sederhana (`<strong\n >`)
tetap dipadatkan seperti sebelumnya. Normalisasi tidak menambah whitespace di
antara tag inline yang berdampingan; konten `<script>`, `<style>`, `<textarea>`,
komentar, dan ekspresi Blade dikecualikan.

Jalankan setelah pemasangan:

```bash
node --test tests/FormatterBladeInlineTags.test.mjs
npm run format:blade
npm run format:check
php artisan test
```

Format on Save yang menggunakan `scripts/format-source.mjs --write --file` akan
memakai normalisasi yang sama. `npm run build` tetap hanya membangun aset, bukan
memformat source.

## TTE v6.11 — Tag pembuka HTML/Blade tanpa atribut

Normalizer sekarang juga merapikan **semua tag pembuka tanpa atribut** yang tanda
`>`-nya dipisahkan oleh baris baru, misalnya `<label\n >` menjadi `<label>`.
Tag seperti `<section>`, `<div>`, `<span>`, dan `<x-label>` ikut tercakup.
Tag **dengan atribut** tetap mengikuti aturan multiline Prettier. Spasi
antar-elemen tidak ditambah atau dihapus. Isi script/style/textarea, komentar,
dan ekspresi Blade dikecualikan.

Untuk menerapkan pada view yang sudah terformat sebelumnya:

```bash
node --test tests/FormatterBladeInlineTags.test.mjs
npm run format:blade
npm run format:check
php artisan test
```

Format on Save harus menjalankan `scripts/format-source.mjs` dari root proyek
(ekstensi Run On Save), bukan format native Prettier yang melewati normalizer.
`npm run build` adalah build Vite dan **tidak** melakukan formatting.


## Standar form HTML/Blade (v6.12)

- Gunakan pasangan label eksplisit (`for`) dan kontrol (`id`) yang sama.
- Label ditempatkan sebagai sibling input di dalam `<div class="form-field">`,
  bukan membungkus `<input>` secara inline dalam form akun/admin.
- Contoh:

  ```blade
  <div class="form-field">
      <label for="login-password">Password</label>
      <input
          id="login-password"
          name="password"
          type="password"
          autocomplete="current-password"
          required
      />
  </div>
  ```

- Pertahankan `name`, `value`, `required`, CSRF, validasi, dan hak akses saat
  melakukan refactoring markup.
- Kode di bawah `resources/views/public/` yang memiliki form bertahap dan
  CSS khusus tidak diubah otomatis oleh patch ini. Audit secara terpisah.
- Jalankan formatter proyek dengan `npm run format:blade` dan tinjau `git diff`.
- `<input>` adalah elemen void HTML5: `/>` bukan tag penutup. Bentuk output
  akhir mengikuti Prettier Blade; fokus standar ini adalah struktur label/input.
