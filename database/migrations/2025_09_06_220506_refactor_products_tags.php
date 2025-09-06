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
        // 1. Drop tags column from products
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'tags')) {
                $table->dropColumn('tags');
            }
        });

        // 2. Create tags table
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // 3. Create product_tag pivot table
        Schema::create('product_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
            $table->unique(['product_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // rollback pivot + tags table
        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('tags');

        // add back tags column in products
        Schema::table('products', function (Blueprint $table) {
            $table->json('tags')->nullable();
        });
    }
};
