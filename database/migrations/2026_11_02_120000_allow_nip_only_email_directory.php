<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('email_directories', function (Blueprint $table) {
            // Pegawai bisa terdaftar hanya dengan NIP; NIK yang tak diketahui tetap NULL.
            $table->text('nik')->nullable()->change();
            $table->index('nip', 'email_directories_nip_lookup_idx');
        });
    }

    public function down(): void {
        Schema::table('email_directories', function (Blueprint $table) {
            $table->dropIndex('email_directories_nip_lookup_idx');
            // Rollback ke NOT NULL akan gagal bila ada baris tanpa NIK.
            // Bersihkan atau ekspor data NIP-only sebelum menjalankan rollback.
            $table->text('nik')->nullable(false)->change();
        });
    }
};
