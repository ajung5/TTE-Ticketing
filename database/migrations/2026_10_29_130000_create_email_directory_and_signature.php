<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_directories', function (Blueprint $table) {
            $table->id();
            $table->char('nik_hash', 64)->unique();
            $table->text('nik'); // Encrypted cast, never searchable as plaintext.
            $table->string('nama', 160)->nullable();
            $table->string('nip', 32)->nullable();
            $table->text('email')->nullable(); // Encrypted cast.
            $table->string('jabatan', 180)->nullable();
            $table->string('nama_unor', 180)->nullable();
            $table->string('unit_kerja', 180)->nullable();
            $table->string('status_pengguna', 80)->nullable();
            $table->string('status_sertifikat', 80)->nullable();
            $table->uuid('sync_batch');
            $table->timestamp('synced_at');
            $table->timestamps();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('consent_accepted_at')->nullable();
            $table->string('signature_path', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn(['consent_accepted_at', 'signature_path']));
        Schema::dropIfExists('email_directories');
    }
};
