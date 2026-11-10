<?php

use App\Models\EmailDirectory;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WaMessageTemplate;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\WaTemplatePresetsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// Source: Current/InstallSchemaAndSeedTest.php
it('creates the complete operational schema without legacy add-column migrations', function () {
    foreach (
        [
            'users',
            'tickets',
            'ticket_activities',
            'wa_notifications',
            'audit_logs',
            'wa_message_templates',
            'email_directories',
            'service_survey_responses',
            'blocked_ticket_attempts',
            'ticket_email_changes',
        ]
        as $table
    ) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
    expect(Schema::hasTable('webhook_events'))->toBeFalse();
    foreach (['role', 'initials', 'deleted_at'] as $column) {
        expect(Schema::hasColumn('users', $column))->toBeTrue();
    }
    foreach (
        [
            'uptd',
            'nik',
            'activation_status',
            'wa_verification_status',
            'certificate_status',
            'certificate_issuance_status',
            'certificate_validity_status',
            'recommended_service',
            'service_alignment_status',
            'actual_process',
            'service_outcome',
            'signature_path',
            'consent_accepted_at',
            'applicant_nik_hash',
            'applicant_nip_hash',
            'duplicate_attempts',
            'last_duplicate_attempt_at',
            'awaiting_applicant_action',
            'directory_email',
            'email_corrected_at',
            'email_corrected_by',
        ]
        as $column
    ) {
        expect(Schema::hasColumn('tickets', $column))->toBeTrue();
    }
    foreach (['nip', 'uptd', 'nik', 'nik_hash', 'sync_batch'] as $column) {
        expect(Schema::hasColumn('email_directories', $column))->toBeTrue();
    }
    expect(Schema::hasColumn('service_survey_responses', 'respondent_name'))->toBeTrue();
});

it(
    'starts with no tickets surveys or directory records and has the current WA presets',
    function () {
        expect(Ticket::count())->toBe(0);
        expect(DB::table('service_survey_responses')->count())->toBe(0);
        expect(DB::table('email_directories')->count())->toBe(0);
        expect(WaMessageTemplate::count())->toBe(count(WaMessageTemplate::TEMPLATE_SERVICE_CODES));
        foreach (WaMessageTemplate::TEMPLATE_SERVICE_CODES as $event => $serviceCode) {
            $this->assertDatabaseHas('wa_message_templates', [
                'event_type' => $event,
                'service_code' => $serviceCode,
            ]);
        }
    },
);

it('keeps baseline seeding idempotent and does not create sample tickets or surveys', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $passwordBefore = $admin->password;
    $preset = WaMessageTemplate::where('event_type', 'completed')
        ->where('service_code', '*')
        ->firstOrFail();
    $preset->update([
        'body' =>
            'Kustom: {nama_pemohon}; tiket {nomor_tiket}; petugas {nama_verifikator}; {hasil_aktual}.',
    ]);
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);
    expect($admin->fresh()->password)->toBe($passwordBefore);
    expect($verifier->fresh()->role)->toBe('verifier');
    expect($preset->fresh()->body)->toContain('Kustom:');
    expect(Ticket::count())->toBe(0);
    expect(DB::table('service_survey_responses')->count())->toBe(0);
});

// Source: TteBaselineSeederTest.php
it(
    'seeds baseline without creating sample tickets or changing existing account passwords',
    function () {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Mas Admin']);
        $verifier = User::factory()->create(['role' => 'verifier']);
        $originalPassword = $admin->password;
        $template = WaMessageTemplate::query()
            ->where('event_type', 'completed')
            ->where('service_code', '*')
            ->firstOrFail();
        $template->update([
            'body' =>
                'Yth {nama_pemohon}, nomor {nomor_tiket}. Petugas {nama_verifikator}. Survei: {survey_url}',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        expect(Ticket::count())->toBe(0);
        expect($admin->fresh()->password)->toBe($originalPassword);
        expect($verifier->fresh()->role)->toBe('verifier');
        expect($template->fresh()->body)->toBe(
            'Yth {nama_pemohon}, nomor {nomor_tiket}. Petugas {nama_verifikator}. Survei: {survey_url}',
        );
        foreach (WaMessageTemplate::TEMPLATE_SERVICE_CODES as $event => $service) {
            $this->assertDatabaseHas('wa_message_templates', [
                'event_type' => $event,
                'service_code' => $service,
            ]);
        }
    },
);

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

// Source: TteFreshInstallV64Test.php
it('creates operational tables without the discontinued Zoho webhook events table', function () {
    foreach (
        [
            'users',
            'password_reset_tokens',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'tickets',
            'ticket_activities',
            'wa_notifications',
            'audit_logs',
            'wa_message_templates',
            'email_directories',
            'service_survey_responses',
            'blocked_ticket_attempts',
            'ticket_email_changes',
        ]
        as $name
    ) {
        expect(Schema::hasTable($name))->toBeTrue();
    }
    expect(Schema::hasTable('webhook_events'))->toBeFalse();
});

it(
    'installs the current v61 ticket schema and defaults new tickets to the internal form source',
    function () {
        foreach (
            [
                'external_submission_id', // masih dipakai untuk idempotency formulir internal
                'applicant_nik_hash',
                'applicant_nip_hash',
                'duplicate_attempts',
                'last_duplicate_attempt_at',
                'awaiting_applicant_action',
                'directory_email',
                'email_corrected_at',
                'email_corrected_by',
            ]
            as $column
        ) {
            expect(Schema::hasColumn('tickets', $column))->toBeTrue();
        }
        $ticket = Ticket::create([
            'nama_user' => 'Pemohon Fresh Install',
            'no_wa' => '6281212345678',
            'layanan' => 'Pendaftaran',
        ]);
        expect($ticket->fresh()->source)->toBe('direct');
    },
);

it('starts empty except for configured template presets and has no sample accounts', function () {
    expect(User::count())->toBe(0);
    expect(Ticket::count())->toBe(0);
    foreach (
        [
            'ticket_activities',
            'wa_notifications',
            'blocked_ticket_attempts',
            'ticket_email_changes',
            'service_survey_responses',
            'email_directories',
        ]
        as $name
    ) {
        expect(DB::table($name)->count())->toBe(0);
    }
    // TestCase menyediakan preset secara independen dari provisioning akun.
    expect(WaMessageTemplate::count())->toBe(count(WaMessageTemplate::TEMPLATE_SERVICE_CODES));
});

it(
    'can repeat baseline seed without overwriting passwords templates or adding sample tickets',
    function () {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'verifier']);
        $password = $admin->password;
        $template = WaMessageTemplate::where('event_type', 'completed')->firstOrFail();
        $template->update(['body' => 'Pesan disesuaikan oleh pengelola {nomor_tiket}']);
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        expect($admin->fresh()->password)->toBe($password);
        expect($template->fresh()->body)->toBe('Pesan disesuaikan oleh pengelola {nomor_tiket}');
        expect(User::count())->toBe(2);
        expect(Ticket::count())->toBe(0);
        expect(DB::table('service_survey_responses')->count())->toBe(0);
    },
);

it('runs WhatsApp preset seeding independently and idempotently', function () {
    $this->seed(WaTemplatePresetsSeeder::class);
    $this->seed(WaTemplatePresetsSeeder::class);
    expect(WaMessageTemplate::count())->toBe(count(WaMessageTemplate::TEMPLATE_SERVICE_CODES));
    foreach (WaMessageTemplate::TEMPLATE_SERVICE_CODES as $event => $service) {
        $this->assertDatabaseHas('wa_message_templates', [
            'event_type' => $event,
            'service_code' => $service,
        ]);
    }
});
