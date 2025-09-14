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
        // roles table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // doctor, clinic, paraprofessional, etc.
            $table->timestamps();
        });

        // role_user pivot
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');
            $table->unsignedBigInteger('role_id');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
        });

        // add active role to users
        Schema::table('users_registration', function (Blueprint $table) {
            $table->unsignedBigInteger('active_role_id')->nullable();
            $table->foreign('active_role_id')->references('id')->on('roles')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_registration', function (Blueprint $table) {
            $table->dropForeign(['active_role_id']);
            $table->dropColumn('active_role_id');
        });

        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
