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
        Schema::create('veterinary_paraprofessional', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->foreign()->references('id')->on('users_registration')->onDelete('cascade');
            $table->string('name_of_institution');
            $table->string('graduation_year');
            $table->string('expected_year_of_graduation')->nullable();
            $table->string('specialty');
            $table->text('list_them');
            $table->string('contact_num');
            $table->string('address');
            $table->string('longitude');
            $table->string('latitude');
            $table->string('role')->default('veterinary_paraprofessional');
            $table->boolean('is_approved')->default('0'); // 0 = pending, 1 = approved
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('veterinary_paraprofessional');
    }
};
