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
        Schema::dropIfExists('user_promotion_plan');
        Schema::create('user_promotion_plan', function (Blueprint $table) {
            $table->id();

            // Match BIGINT UNSIGNED from users.id
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');

            $table->string('role')->nullable();

            // Match BIGINT UNSIGNED from promotion_plan.id
            $table->unsignedBigInteger('promotion_plan_id');
            $table->foreign('promotion_plan_id')->references('id')->on('promotion_plan')->onDelete('cascade');

            $table->decimal('price', 10, 2)->nullable();
            $table->integer('no_of_products')->default(0);

            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();

            $table->enum('status', ['pending', 'active', 'expired'])->default('pending');

            $table->timestamps();

            $table->index(['user_id', 'promotion_plan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_promotion_plan');
    }
};
