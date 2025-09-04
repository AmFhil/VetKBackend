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
        Schema::table('rating', function (Blueprint $table) {
            $table->dropForeign(['rated_user_id']);
            $table->dropColumn('rated_user_id');
            $table->morphs('rateable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('rating', function (Blueprint $table) {
            $table->dropForeign(['rateable_id', 'rateable_type']);
            $table->unsignedBigInteger('rated_user_id')->after('user_id');
            $table->foreign('rated_user_id')->references('id')->on('users_registration')->onDelete('cascade');
            $table->dropMorphs('rateable');
        });
    }
};
