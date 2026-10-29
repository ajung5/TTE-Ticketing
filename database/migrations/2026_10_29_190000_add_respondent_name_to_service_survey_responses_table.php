<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('service_survey_responses', function (Blueprint $table) {
            // Nullable agar respons sebelum pembaruan tetap dapat dibaca.
            $table->string('respondent_name', 160)->nullable();
        });
    }

    public function down(): void {
        Schema::table('service_survey_responses', function (Blueprint $table) {
            $table->dropColumn('respondent_name');
        });
    }
};
