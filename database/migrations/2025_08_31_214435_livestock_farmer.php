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
        Schema::create('livestock_farmer', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->foreign()->references('id')->on('users_registration')->onDelete('cascade');
            $table->string('role')->default('livestock_farmer');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('livestock_farmer');
    }
};
