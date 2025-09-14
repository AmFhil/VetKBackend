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
        Schema::table('user_promotion_plan', function (Blueprint $table) {
            //
            $table->string('promotion_title')->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_promotion_plan', function (Blueprint $table) {
            //
            $table->dropColumn('promotion_title');
        });
    }
};
