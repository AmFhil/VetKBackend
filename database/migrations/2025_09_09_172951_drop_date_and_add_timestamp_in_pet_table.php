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
        if (!Schema::hasTable('pet')) {
            Schema::create('pet', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('pet_name')->nullable();
                $table->string('pet_id')->nullable();
                $table->string('specie')->nullable();
                $table->string('breed')->nullable();
                $table->string('sex')->nullable();
                $table->string('age')->nullable();
                $table->string('picture')->nullable();
                $table->string('location')->nullable();
                $table->timestamps();
            });
            return;
        }

        Schema::table('pet', function (Blueprint $table) {
            //
            $table->dropColumn('date');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pet', function (Blueprint $table) {
            //
            $table->dropColumn(['created_at', 'updated_at']);

            $table->date('date');
        });
    }
};
