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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');   // the patient
            $table->unsignedBigInteger('doctor_id'); // the doctor

            $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');
            $table->foreign('doctor_id')->references('id')->on('users_registration')->onDelete('cascade');

            $table->dateTime('appointment_at'); // date & time in one field
            $table->enum('status', ['scheduled', 'cancelled', 'completed'])->default('scheduled');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
