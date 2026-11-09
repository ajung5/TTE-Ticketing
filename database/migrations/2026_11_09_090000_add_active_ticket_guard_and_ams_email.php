<?php

use App\Models\Ticket;
use App\Services\EmailDirectoryService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tickets', function (Blueprint $table) {
            $table->char('applicant_nik_hash', 64)->nullable()->index();
            $table->char('applicant_nip_hash', 64)->nullable()->index();
            $table->unsignedInteger('duplicate_attempts')->default(0);
            $table->timestamp('last_duplicate_attempt_at')->nullable();
            $table->boolean('awaiting_applicant_action')->default(false);
            $table->text('directory_email')->nullable();
            $table->timestamp('email_corrected_at')->nullable();
            $table->foreignId('email_corrected_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('blocked_ticket_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('external_submission_id', 128)->unique();
            $table->string('source', 20);
            $table->string('requested_service', 40);
            $table->timestamps();
        });

        Schema::create('ticket_email_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('old_email'); // encrypted Eloquent cast
            $table->text('new_email'); // encrypted Eloquent cast
            $table->string('reason', 500);
            $table->timestamps();
            $table->index(['ticket_id', 'created_at']);
        });

        // Legacy backfill; never attempt an SQL comparison against encrypted NIK.
        // Preserve old direct-form email as the directory snapshot.
        $guard = app(\App\Services\ActiveTicketGuard::class);
        Ticket::query()->where('source', '!=', 'simulated')->chunkById(200, function ($tickets) use ($guard) {
            foreach ($tickets as $ticket) {
                $f = $guard->fingerprints((string) ($ticket->nik ?? ''), (string) ($ticket->nip ?? ''));
                $data = [
                    'applicant_nik_hash' => $f['nik'],
                    'applicant_nip_hash' => $f['nip']
                ];
                if ($ticket->source === 'direct' && $ticket->email) {
                    $data['directory_email'] = $ticket->email;
                }
                $ticket->forceFill($data)->saveQuietly();
            }
        });
    }

    public function down(): void {
        Schema::dropIfExists('ticket_email_changes');
        Schema::dropIfExists('blocked_ticket_attempts');
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['email_corrected_by']);
            $table->dropIndex(['applicant_nik_hash']);
            $table->dropIndex(['applicant_nip_hash']);
            $table->dropColumn([
                'applicant_nik_hash', 'applicant_nip_hash', 'duplicate_attempts',
                'last_duplicate_attempt_at', 'awaiting_applicant_action',
                'directory_email', 'email_corrected_at', 'email_corrected_by'
            ]);
        });
    }
};
