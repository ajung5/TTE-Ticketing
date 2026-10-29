# Daftar perubahan TTE-Ticketing (commit d806883)

## File baru

- `app/Http/Controllers/WaTemplateController.php`
- `app/Models/WaMessageTemplate.php`
- `database/migrations/2026_10_29_100000_create_wa_message_templates_table.php`
- `database/seeders/DemoTicketsSeeder.php`
- `resources/views/admin/wa-templates.blade.php`
- `public/js/tte.js`
- `tests/Feature/WaTemplateManagementTest.php`
- `docs/PANDUAN-UPDATE-UI-PRESET-DUMMY.md`
- `docs/DAFTAR-FILE-PATCH.md`

## File lama yang berubah

- `routes/web.php` — route admin preset
- `app/Services/WhatsAppMessage.php` — pesan menggunakan preset dari database
- `app/Http/Controllers/TicketController.php` — pratinjau tindak lanjut dan penolakan WA pada dummy
- `database/seeders/DatabaseSeeder.php` — memanggil seeder 100 tiket hanya di lokal
- `resources/views/layouts/app.blade.php` — sidebar dan menu preset
- `resources/views/admin/dashboard.blade.php` — tampilan statistik baru
- `resources/views/tickets/index.blade.php` — tampilan daftar tiket baru
- `resources/views/tickets/show.blade.php` — kartu WA + copy
- `public/css/tte.css` — UI refresh responsif
