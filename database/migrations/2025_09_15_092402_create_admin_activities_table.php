<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop table if it already exists
        Schema::dropIfExists('admin_activities');

        Schema::create('admin_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('action');
            $table->string('title')->nullable();
            $table->text('detail')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('admin')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activities');
    }
};
