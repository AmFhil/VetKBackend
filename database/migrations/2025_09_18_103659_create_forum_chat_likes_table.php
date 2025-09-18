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
        Schema::create('forum_chat_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_chat_id')
                ->constrained('forum_chats')
                ->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('user_type');
            $table->timestamps();

            $table->unique(['forum_chat_id', 'user_id', 'user_type']);


            $table->index('forum_chat_id');
            $table->index(['user_id', 'forum_chat_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forum_chat_likes');
    }
};
