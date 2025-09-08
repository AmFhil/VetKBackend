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
        Schema::create('blog_chat_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_chat_id')->constrained('blog_chats')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->string('user_type');
            $table->unsignedBigInteger('parent_id')->nullable(); // for nested replies
            $table->text('comment');
            $table->enum('flag', ['none', 'spam', 'abuse', 'hidden'])->default('none'); // moderation flags
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('blog_chat_comments')->onDelete('cascade');
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('blog_chat_comments');
    }
};
