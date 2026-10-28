<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('verifier');
            $table->string('initials', 12)->nullable();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 36)->nullable()->unique();
            $table->string('external_submission_id', 128)->nullable()->unique();
            $table->string('nama_user', 160);
            $table->string('no_wa', 30);
            $table->string('layanan', 40);
            $table->string('status', 24)->default('Open');
            $table->string('source', 20)->default('zoho');
            $table->string('nip', 32)->nullable();
            $table->string('opd', 180)->nullable();
            $table->string('jabatan', 180)->nullable();
            $table->string('email', 180)->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('resolution_note')->nullable();
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

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('external_submission_id', 128)->nullable();
            $table->string('outcome', 24);
            $table->string('payload_sha256', 64)->nullable();
            $table->timestamps();
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

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('wa_notifications');
        Schema::dropIfExists('ticket_activities');
        Schema::dropIfExists('tickets');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'initials']));
    }
};
