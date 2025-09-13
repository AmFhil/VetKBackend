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
        // Drop table if it exists
        Schema::dropIfExists('ads_promotion');

        // Recreate table
        Schema::create('ads_promotion', function (Blueprint $table) {
            $table->id();

            // User + role
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');
            $table->string('role')->nullable();

            // Store relation
            $table->unsignedBigInteger('store_id')->nullable();
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');

            // Link to user promotion plan
            $table->unsignedBigInteger('user_promotion_plan_id')->nullable();
            $table->foreign('user_promotion_plan_id')->references('id')->on('user_promotion_plan')->onDelete('cascade');

            // Product details
            $table->string('product_title');
            $table->string('product_category')->nullable(); // free text
            $table->string('location')->nullable();
            $table->text('product_description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('availability')->default(true);
            $table->boolean('suspended')->default(false);
            $table->integer('available_units')->default(0);
            $table->json('tags')->nullable();
            $table->json('images')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'store_id', 'user_promotion_plan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads_promotion');
    }
};
