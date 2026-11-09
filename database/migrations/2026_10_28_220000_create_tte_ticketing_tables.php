<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Skema inti tiket TTE. Penambahan v6.1 ada pada migration 2026_11_09. */
return new class extends Migration {
    public function up(): void {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 36)->nullable()->unique();
            $table->string('external_submission_id', 128)->nullable()->unique();
            $table->string('nama_user', 160);
            $table->string('no_wa', 30);
            $table->string('layanan', 40);
            $table->string('status', 24)->default('Open');
            $table->string('source', 20)->default('direct');
            $table->string('nip', 32)->nullable();
            $table->string('opd', 180)->nullable();
            // NULL: data lama belum mencatat UPTD; kosong: pemohon tanpa UPTD.
            $table->text('uptd')->nullable();
            $table->string('jabatan', 180)->nullable();
            $table->string('email', 180)->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('resolution_note')->nullable();
            // NIK disimpan encrypted melalui cast model, bukan plaintext.
            $table->text('nik')->nullable();
            $table->string('wa_verification_status', 20)->default('unchecked');
            $table->string('wa_verification_note', 500)->nullable();
            $table->timestamp('wa_checked_at')->nullable();
            $table->foreignId('wa_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('certificate_status', 20)->default('unchecked');
            $table->string('activation_status', 20)->nullable();
            $table->string('certificate_issuance_status', 20)->nullable();
            $table->string('certificate_validity_status', 20)->nullable();
            $table->string('recommended_service', 40)->nullable();
            $table->string('service_alignment_status', 20)->nullable();
            $table->string('actual_process', 40)->nullable();
            $table->string('service_outcome', 40)->nullable();
            $table->timestamp('consent_accepted_at')->nullable();
            $table->string('signature_path', 255)->nullable();
            $table->timestamps();
            $table->index(['status', 'claimed_by']);
        });

        Schema::create('ticket_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('wa_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('verifier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 32)->default('completed');
            $table->text('message');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'event_type']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('target_type', 64)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('audit_logs');
        // Kompatibilitas rollback database lama yang dahulu memiliki tabel webhook.
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('wa_notifications');
        Schema::dropIfExists('ticket_activities');
        Schema::dropIfExists('tickets');
    }
};
