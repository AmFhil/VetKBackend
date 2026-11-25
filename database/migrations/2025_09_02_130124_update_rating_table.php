<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rating')) {
            Schema::create('rating', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('rated_user_id')->nullable();
                $table->integer('rating')->default(5);
                $table->string('comment')->nullable();
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');
            });
            return;
        }

        Schema::table('rating', function (Blueprint $table) {
            // truncate exiting data
            DB::table('rating')->truncate();

            // Remove columns
            $table->dropColumn(['date', 'user_id', 'rated_user_id']);

            // Add columns
            $table->unsignedBigInteger('user_id')->after('id');
            $table->unsignedBigInteger('rated_user_id')->after('user_id');
            $table->string('comment')->nullable()->after('rating');
            $table->timestamps()->after('comment');
            

            // Add foreign keys
            $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');
            $table->foreign('rated_user_id')->references('id')->on('users_registration')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('rating', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['user_id']);
            $table->dropForeign(['rated_user_id']);

            // Drop added columns
            $table->dropColumn(['user_id', 'rated_user_id', 'created_at', 'updated_at', 'comment']);

            // Add dropped columns back
            $table->string('date')->nullable();
        });
    }
};
