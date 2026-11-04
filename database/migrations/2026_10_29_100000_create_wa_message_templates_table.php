<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Migration hanya mendefinisikan skema; sebelas preset aktif diisi oleh seeder. */
return new class extends Migration {
    public function up(): void {
        Schema::create('wa_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('service_code', 40);
            // certificate_not_issued > 20 karakter; gunakan 40 agar MySQL/PG kompatibel.
            $table->string('event_type', 40);
            $table->text('body');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['service_code', 'event_type']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('wa_message_templates');
    }
};
