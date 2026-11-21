<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ticket_reset_resends', function (Blueprint $table) {
            // Rows written before this migration were exclusively reset-passphrase resends.
            $table->string('link_type', 32)->default('reset_link');
        });
    }

    public function down(): void {
        Schema::table('ticket_reset_resends', function (Blueprint $table) {
            $table->dropColumn('link_type');
        });
    }
};
