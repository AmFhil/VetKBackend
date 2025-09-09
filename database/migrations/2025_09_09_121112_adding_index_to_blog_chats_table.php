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
        Schema::table('blog_chats', function (Blueprint $table) {
            //
            $table->index(['status', 'created_at']); // for listing blogs
            $table->index('slug'); // for showWithSlug
            $table->index('user_id'); // analytics per author
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_chats', function (Blueprint $table) {
            //
        });
    }
};
