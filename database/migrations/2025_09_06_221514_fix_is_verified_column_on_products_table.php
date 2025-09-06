<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Ensure the correct column exists
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('is_featured');
            }
        });

        // 2) If the typo column exists, copy values into the correct column
        if (Schema::hasColumn('products', 'ís_verified')) {
            // Copy old values to the new column (MySQL/MariaDB syntax with backticks)
            DB::statement('UPDATE `products` SET `is_verified` = COALESCE(`ís_verified`, 0)');

            // 3) Drop the typo column
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('ís_verified');
            });
        }
    }

    public function down(): void
    {
        // Recreate the old (typo) column
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'ís_verified')) {
                $table->boolean('ís_verified')->default(false)->after('is_featured');
            }
        });

        // Copy values back from the correct column if it exists
        if (Schema::hasColumn('products', 'is_verified')) {
            DB::statement('UPDATE `products` SET `ís_verified` = COALESCE(`is_verified`, 0)');

            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('is_verified');
            });
        }
    }
};
