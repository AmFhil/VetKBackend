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
        Schema::table('admin', function (Blueprint $table) {
            //
            $table->dropColumn(['profile_picture','date']);
            $table->string('profile_picture')->nullable()->after('location');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin', function (Blueprint $table) {
            //
            $table->dropColumn(['profile_picture', 'created_at', 'updated_at']);
            $table->string('profile_picture')->nullable()->after('location');
            $table->date('date');
        });
    }
};
