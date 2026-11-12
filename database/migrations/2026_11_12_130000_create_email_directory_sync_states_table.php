<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('email_directory_sync_states', function (Blueprint $table) {
            $table->string('name', 64)->primary();
            $table->timestamp('last_success_at');
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamp('updated_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('email_directory_sync_states');
    }
};
