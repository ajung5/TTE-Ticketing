<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('service_alignment_status', 20)->nullable();
            $table->string('actual_process', 40)->nullable();
            $table->string('service_outcome', 40)->nullable();
        });
    }

    public function down(): void {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['service_alignment_status', 'actual_process', 'service_outcome']);
        });
    }
};
