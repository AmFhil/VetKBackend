<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::dropIfExists('views');

        Schema::create('views', function (Blueprint $table) {
            $table->id();

            // For logged-in users
            $table->unsignedBigInteger('user_id')->nullable();

            // For guests
            $table->string('session_id')->nullable(); // Laravel session id
            $table->string('ip_address')->nullable(); // fallback
            $table->string('user_agent')->nullable(); // optional, to avoid same IP counting

            // Polymorphic fields
            $table->morphs('viewable'); // viewable_id, viewable_type

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');

            // prevent duplicate logged-in views
            $table->unique(['user_id', 'viewable_id', 'viewable_type']);

            // prevent duplicate guest views
            $table->unique(['session_id', 'viewable_id', 'viewable_type']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('views');
    }
};
