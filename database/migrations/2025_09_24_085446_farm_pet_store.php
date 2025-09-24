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
        

        Schema::table('farm', function (Blueprint $table) {
            // Add the new user_id column and foreign key constraint
            $table->unsignedBigInteger('user_id')->after('id');
            $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');
        });

        // Update the 'pet' table
        Schema::table('pet', function (Blueprint $table) {
            // Drop the old user_id column if it exists
            $table->dropColumn('user_id');
        });

        Schema::table('pet', function (Blueprint $table) {
            // Add the new user_id column, foreign key, and location column
            $table->unsignedBigInteger('user_id')->after('id');
            $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');
            $table->string('location')->nullable()->after('picture');
        });

        // Update the 'store' table
        Schema::table('store', function (Blueprint $table) {
            // Drop the old user_id column if it exists
            $table->dropColumn('user_id');
        });

        Schema::table('store', function (Blueprint $table) {
            // Add the new user_id column and foreign key constraint
            $table->unsignedBigInteger('user_id')->after('id');
            $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the changes in the 'store' table
        Schema::table('store', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->string('user_id'); // Re-add the old column, adjust type as needed
        });

        // Revert the changes in the 'pet' table
        Schema::table('pet', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropColumn('location'); // Drop the location column
            $table->string('user_id'); // Re-add the old column, adjust type as needed
        });

        // Revert the changes in the 'farm' table
        Schema::table('farm', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->string('user_id'); // Re-add the old column, adjust type as needed
        });
    }
};
