<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\WaMessageTemplate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the complete operational schema without legacy add-column migrations', function () {
    foreach (
        [
            'users',
            'tickets',
            'ticket_activities',
            'wa_notifications',
            'webhook_events',
            'audit_logs',
            'wa_message_templates',
            'email_directories',
            'service_survey_responses',
        ]
        as $table
    ) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
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
