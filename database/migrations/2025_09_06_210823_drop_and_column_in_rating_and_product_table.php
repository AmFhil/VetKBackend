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
        // 1. Update products table
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('product_type')
                ->default(false)
                ->after('store_id'); // adjust "after" to a suitable column
        });

        // 2. Update rates table
        Schema::table('rating', function (Blueprint $table) {
            if (Schema::hasColumn('rating', 'user_role')) {
                $table->dropColumn('user_role');
            }
            if (Schema::hasColumn('rating', 'type')) {
                $table->dropColumn('type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // rollback for products
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('product_type');
        });

        // rollback for rates
        Schema::table('rating', function (Blueprint $table) {
            $table->string('user_role')->nullable();
            $table->string('type')->nullable();
        });
    }
};
