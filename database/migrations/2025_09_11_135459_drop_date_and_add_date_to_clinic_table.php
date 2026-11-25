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
        if (!Schema::hasTable('clinic')) {
            Schema::create('clinic', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('license_number')->nullable();
                $table->string('license_status')->nullable();
                $table->string('clinic_name')->nullable();
                $table->text('clinic_speciality')->nullable();
                $table->string('email')->nullable();
                $table->string('phone_number')->nullable();
                $table->string('location')->nullable();
                $table->boolean('availability')->default(true);
                $table->string('picture')->nullable();
                $table->string('longitude')->nullable();
                $table->string('latitude')->nullable();
                $table->timestamps();
            });
            return;
        }

        Schema::table('clinic', function (Blueprint $table) {
            //
            $table->string('longitude');
            $table->string('latitude');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinic', function (Blueprint $table) {
            //
            $table->dropColumn(['longitude','latitude','created_at','updated_at']);


        });
    }
};
