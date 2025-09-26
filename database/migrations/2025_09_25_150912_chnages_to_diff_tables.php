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
        Schema::table('clinic', function (Blueprint $table) {
            // Drop the old user_id column if it exists
            $table->dropColumn('user_id');
        });

        Schema::table('clinic', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('user_id')->after('id');
            $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');
            $table->boolean('disabled')->default(false)->after('latitude');
        });

        Schema::table('farm', function (Blueprint $table) {
           $table->boolean('disabled')->default(false)->after('description');
        });

        Schema::table('cases', function (Blueprint $table) {
           $table->boolean('disabled')->default(false)->after('picture');
        });

        Schema::table('pet', function (Blueprint $table) {
           $table->boolean('disabled')->default(false)->after('location');
        });

        Schema::table('products', function (Blueprint $table) {
           $table->boolean('disabled')->default(false)->after('is_verified');
        });

        Schema::table('store', function (Blueprint $table) {
            $table->boolean('disabled')->default(false)->after('latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinic', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropColumn('disabled');
            $table->string('user_id'); //
        });

        Schema::table('farm', function (Blueprint $table) {
            //
            $table->dropColumn('disabled');
        });

        Schema::table('cases', function (Blueprint $table) {
            //
            $table->dropColumn('disabled');
        });

        Schema::table('pet', function (Blueprint $table) {
            //
            $table->dropColumn('disabled');
        });

        Schema::table('products', function (Blueprint $table) {
            //
            $table->dropColumn('disabled');
        });

        Schema::table('store', function (Blueprint $table) {
            //
            $table->dropColumn('disabled');
        });
    }
};
