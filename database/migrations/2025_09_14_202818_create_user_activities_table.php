<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old table if it exists
        Schema::dropIfExists('user_activity');

        Schema::create('user_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('action'); // e.g. created_post, liked_comment
            $table->string('title')->nullable();
            $table->text('detail')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users_registration')
                ->onDelete('cascade');

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activities');
    }
};
