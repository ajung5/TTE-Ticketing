<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Direktori email pemerintah; kolom tanda tangan tiket sudah menjadi bagian skema tiket. */
return new class extends Migration {
    public function up(): void {
        Schema::create('email_directories', function (Blueprint $table) {
            $table->id();
            $table->char('nik_hash', 64)->unique();
            // NIP-only dibolehkan; NIK dan email memakai encrypted casts.
            $table->text('nik')->nullable();
            $table->string('nama', 160)->nullable();
            $table->string('nip', 32)->nullable();
            $table->text('email')->nullable();
            $table->string('jabatan', 180)->nullable();
            $table->string('nama_unor', 180)->nullable();
            $table->string('unit_kerja', 180)->nullable();
            $table->text('uptd')->nullable();
            $table->string('status_pengguna', 80)->nullable();
            $table->string('status_sertifikat', 80)->nullable();
            $table->uuid('sync_batch');
            $table->timestamp('synced_at');
            $table->timestamps();
            $table->index('nip', 'email_directories_nip_lookup_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('email_directories');
    }
};
