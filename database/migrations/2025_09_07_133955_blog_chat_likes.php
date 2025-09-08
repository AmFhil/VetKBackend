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
        Schema::create('blog_chat_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_chat_id')->constrained('blog_chats')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->string('user_type');
            $table->timestamps();

            $table->unique(['blog_chat_id', 'user_id']); // prevent multiple likes from same user
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('blog_chat_likes');
    }
};
