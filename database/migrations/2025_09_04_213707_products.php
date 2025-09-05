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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('store_id');
            $table->string('product_name');
            $table->string('category');
            $table->text('description');
            $table->json('tags');
            $table->string('location');
            $table->decimal('price', 8, 2);
            $table->string('image1');
            $table->string('image2')->nullable();
            $table->string('image3')->nullable();
            $table->string('image4')->nullable();
            $table->boolean('availability')->default(true);
            $table->integer('available_unit')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('ís_verified')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('store')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('products');
    }
};
