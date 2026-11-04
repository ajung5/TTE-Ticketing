<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kompatibilitas untuk database yang pernah menggunakan VARCHAR(20).
 * Fresh install sudah memakai VARCHAR(40). Tidak menghapus template kustom.
 */
return new class extends Migration {
    public function up(): void {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true)) {
            Schema::table('wa_message_templates', function (Blueprint $table) {
                $table->string('event_type', 40)->change();
            });
        }
    }

    public function down(): void {
        // Tidak dikecilkan lagi: nilai event lebih dari 20 karakter harus tetap utuh.
    }
};
