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
        Schema::create('forum_chat_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_chat_id')
                ->constrained('forum_chats')
                ->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('user_type');
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->text('comment');
            $table->enum('flag', ['none', 'spam', 'abuse', 'hidden'])->default('none')->index();
            $table->timestamps();
            $table->softDeletes(); // optional

            $table->foreign('parent_id')
                ->references('id')
                ->on('forum_chat_comments')
                ->onDelete('cascade');

            // Optimize threaded lookups
            $table->index(['forum_chat_id', 'parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forum_chat_comments');
    }
};
