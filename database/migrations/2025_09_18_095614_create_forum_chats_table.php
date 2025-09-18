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
        Schema::create('forum_chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('user_type');
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('content');
            $table->json('images')->nullable(); 
            $table->enum('status', ['published', 'archived'])->default('published')->index();

            // counters
            $table->unsignedBigInteger('views_count')->default(0)->index();
            $table->unsignedBigInteger('shares_count')->default(0);
            $table->unsignedBigInteger('comments_count')->default(0)->index();
            $table->unsignedBigInteger('likes_count')->default(0)->comment();

            $table->timestamps();
            $table->softDeletes(); // optional for moderation

            // text search optimization
            $table->fullText(['title', 'content']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forum_chats');
    }
};
