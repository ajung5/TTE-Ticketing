<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ticket_daily_sequences', function (Blueprint $table) {
            $table->date('queue_date')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('created_at', 'tickets_queue_created_at_index');
        });
    }

    public function down(): void {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_queue_created_at_index');
        });
        Schema::dropIfExists('ticket_daily_sequences');
    }
};
