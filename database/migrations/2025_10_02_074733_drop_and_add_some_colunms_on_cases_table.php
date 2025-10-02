<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['disease_diagnosis', 'clinic_physical_address', 'mobile_veterinarian']);

            $table->date('date_presented')->nullable()->after('date_occurred');
            $table->string('temperature')->nullable()->after('clinical_signs');
            $table->string('heart_rate')->nullable()->after('temperature');
            $table->string('weight')->nullable()->after('heart_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['date_presented', 'temperature', 'heart_rate', 'weight']);

            $table->string('disease_diagnosis')->nullable()->after('differential_diagnosis');
            $table->string('clinic_physical_address')->nullable()->after('treatment_regimen');
            $table->string('mobile_veterinarian')->nullable()->after('clinic_physical_address');
        });
    }
};
