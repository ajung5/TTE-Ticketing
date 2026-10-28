# Daftar Perubahan — TTE-Ticketing Fresh Install

**Repo target**: `ajung5/TTE-Ticketing` (Laravel `^13.17`, PHP `^8.3`). File berikut adalah **delta terhadap fresh install**, bukan aplikasi Laravel lengkap.

## File yang menggantikan file lama (4)

- `bootstrap/app.php` — mengaktifkan routing API `/api/tickets/zoho`, tetap tanpa Sanctum.
- `routes/web.php` — autentikasi, dashboard, antrean, self-claim, pengelolaan tiket, admin read-only, reset password.
- `app/Models/User.php` — role `admin`/`verifier`, inisial.
- `database/seeders/DatabaseSeeder.php` — akun demo dan tiket lokal tanpa menghapus data eksisting.


## File baru

- `routes/api.php` — endpoint webhook Zoho.
- `database/migrations/2026_10_28_220000_create_tte_ticketing_tables.php` — create tables dari nol + role user.
- `app/Http/Controllers/{AdminController,AuthController,DashboardController,TicketController,ZohoWebhookController}.php`
- `app/Models/{Ticket,TicketActivity,WaNotification}.php`
- `app/Services/WhatsAppMessage.php`
- `config/tte.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/auth/{login,profile,reset-password}.blade.php`
- `resources/views/admin/dashboard.blade.php`
- `resources/views/tickets/{index,show}.blade.php`
- `public/css/tte.css`
- `tests/Feature/TteManagementTest.php`
- `docs/{PANDUAN-LOCALHOST,DAFTAR-PERUBAHAN}.md`, `docs/zoho-payload.example.json`

**Catatan**: jangan copy file migrasi lama `2026_10_28_210000_expand_tte_management.php`, karena file tersebut mengubah tabel `tickets` yang belum ada di repo baru.
