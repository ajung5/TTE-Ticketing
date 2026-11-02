<?php

use App\Models\EmailDirectory;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WaMessageTemplate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds baseline without creating sample tickets or changing existing account passwords', function () {
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Mas Admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $originalPassword = $admin->password;
    $template = WaMessageTemplate::query()->where('event_type', 'completed')->where('service_code', '*')->firstOrFail();
    $template->update(['body' => 'Yth {nama_pemohon}, nomor {nomor_tiket}. Petugas {nama_verifikator}. Survei: {survey_url}']);

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Ticket::count())->toBe(0);
    expect($admin->fresh()->password)->toBe($originalPassword);
    expect($verifier->fresh()->role)->toBe('verifier');
    expect($template->fresh()->body)->toBe('Yth {nama_pemohon}, nomor {nomor_tiket}. Petugas {nama_verifikator}. Survei: {survey_url}');
    foreach (WaMessageTemplate::TEMPLATE_SERVICE_CODES as $event => $service) {
        $this->assertDatabaseHas('wa_message_templates', ['event_type' => $event, 'service_code' => $service]);
    }
});

it('does not delete existing tickets or directory records if seeder is rerun', function () {
    User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'verifier']);
    $ticket = Ticket::create([
        'nama_user' => 'Pemohon Manual',
        'no_wa' => '6281212345678',
        'layanan' => 'Pendaftaran',
        'status' => 'Open',
        'source' => 'direct',
    ]);
    $directory = EmailDirectory::create([
        'nik_hash' => hash('sha256', 'demo-identifier'),
        'nik' => '3273010101010001',
        'email' => 'pemohon@subang.go.id',
        'sync_batch' => (string) \Illuminate\Support\Str::uuid(),
        'synced_at' => now(),
    ]);

    $this->seed(DatabaseSeeder::class);

    expect(Ticket::whereKey($ticket->id)->exists())->toBeTrue();
    expect(EmailDirectory::whereKey($directory->id)->exists())->toBeTrue();
});
