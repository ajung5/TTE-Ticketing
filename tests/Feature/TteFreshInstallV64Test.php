<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\WaMessageTemplate;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\WaTemplatePresetsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates operational tables without the discontinued Zoho webhook events table', function () {
    foreach ([
        'users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs', 'tickets', 'ticket_activities',
        'wa_notifications', 'audit_logs', 'wa_message_templates', 'email_directories',
        'service_survey_responses', 'blocked_ticket_attempts', 'ticket_email_changes'
    ] as $name) {
        expect(Schema::hasTable($name))->toBeTrue();
    }
    expect(Schema::hasTable('webhook_events'))->toBeFalse();
});

it('installs the current v61 ticket schema and defaults new tickets to the internal form source', function () {
    foreach ([
        'external_submission_id', // masih dipakai untuk idempotency formulir internal
        'applicant_nik_hash', 'applicant_nip_hash', 'duplicate_attempts',
        'last_duplicate_attempt_at', 'awaiting_applicant_action',
        'directory_email', 'email_corrected_at', 'email_corrected_by'
    ] as $column) {
        expect(Schema::hasColumn('tickets', $column))->toBeTrue();
    }
    $ticket = Ticket::create([
        'nama_user' => 'Pemohon Fresh Install',
        'no_wa' => '6281212345678',
        'layanan' => 'Pendaftaran',
    ]);
    expect($ticket->fresh()->source)->toBe('direct');
});

it('starts empty except for configured template presets and has no sample accounts', function () {
    expect(User::count())->toBe(0);
    expect(Ticket::count())->toBe(0);
    foreach ([
        'ticket_activities', 'wa_notifications', 'blocked_ticket_attempts',
        'ticket_email_changes', 'service_survey_responses', 'email_directories'
    ] as $name) {
        expect(DB::table($name)->count())->toBe(0);
    }
    // TestCase menyediakan preset secara independen dari provisioning akun.
    expect(WaMessageTemplate::count())->toBe(count(WaMessageTemplate::TEMPLATE_SERVICE_CODES));
});

it('can repeat baseline seed without overwriting passwords templates or adding sample tickets', function () {
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
});

it('runs WhatsApp preset seeding independently and idempotently', function () {
    $this->seed(WaTemplatePresetsSeeder::class);
    $this->seed(WaTemplatePresetsSeeder::class);
    expect(WaMessageTemplate::count())->toBe(count(WaMessageTemplate::TEMPLATE_SERVICE_CODES));
    foreach (WaMessageTemplate::TEMPLATE_SERVICE_CODES as $event => $service) {
        $this->assertDatabaseHas('wa_message_templates', [
            'event_type' => $event,
            'service_code' => $service
        ]);
    }
});
