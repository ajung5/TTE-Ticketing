<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tickets', function (Blueprint $table) {
            // Nullable: data historis 'issued/active' bukan bukti masa berlaku saat ini.
            $table->string('certificate_validity_status', 20)->nullable();
        });
    }

    public function down(): void {
        Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn('certificate_validity_status'));
    }
};
