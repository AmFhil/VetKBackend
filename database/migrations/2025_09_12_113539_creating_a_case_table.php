<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cases');
        Schema::create('cases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users_registration')
                  ->onDelete('cascade');

            $table->string('case_id')->unique();
            $table->string('case_title');
            $table->string('client_name');
            $table->string('client_phone_number', 20);

            // Pet or farm
            $table->string('pet_or_farm');
            $table->string('pet_name')->nullable();
            $table->string('specie')->nullable();
            $table->string('breed')->nullable();
            $table->string('pet_number')->nullable();

            // Farm details
            $table->string('farm_name')->nullable();
            $table->string('type_of_livestock')->nullable();
            $table->integer('number_of_livestock')->nullable();
            $table->integer('number_of_workers')->nullable();

            // Common fields
            $table->integer('age')->nullable();
            $table->string('sex')->nullable();
            $table->string('location')->nullable();
            $table->text('other_details')->nullable();

            $table->date('date_occurred');
            $table->longText('history')->nullable();
            $table->json('clinical_signs')->nullable();

            $table->text('tentative_diagnosis')->nullable();
            $table->text('differential_diagnosis')->nullable();
            $table->text('disease_diagnosis')->nullable();
            $table->string('lab_confirm')->nullable();
            $table->string('mortality')->nullable();

            $table->json('treatment_regimen')->nullable();

            $table->string('clinic_physical_address')->nullable();
            $table->string('mobile_veterinarian')->nullable();
            $table->string('picture')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
