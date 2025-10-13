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
        Schema::table('users_registration', function (Blueprint $table) {
            $table->softDeletes()->after('remember_token');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes()->after('disabled');
        });

        Schema::table('store', function (Blueprint $table) {
            $table->softDeletes()->after('disabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_registration', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });

        Schema::table('store', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
