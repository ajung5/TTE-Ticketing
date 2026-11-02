<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('email_directories', 'uptd')) {
            Schema::table('email_directories', function (Blueprint $table) {
                $table->text('uptd')->nullable();
            });
        }
        if (!Schema::hasColumn('tickets', 'uptd')) {
            Schema::table('tickets', function (Blueprint $table) {
                // NULL: tiket lama, string kosong: tidak memiliki UPTD saat pengajuan.
                $table->text('uptd')->nullable();
            });
        }
    }

    public function down(): void {
        if (Schema::hasColumn('tickets', 'uptd')) {
            Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn('uptd'));
        }
        if (Schema::hasColumn('email_directories', 'uptd')) {
            Schema::table('email_directories', fn (Blueprint $table) => $table->dropColumn('uptd'));
        }
    }
};
