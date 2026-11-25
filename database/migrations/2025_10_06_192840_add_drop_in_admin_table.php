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
        if (!Schema::hasTable('admin')) {
            Schema::create('admin', function (Blueprint $table) {
                $table->id();
                $table->string('staff_id')->nullable();
                $table->string('password');
                $table->string('permission_level')->default('super_admin');
                $table->string('email')->unique();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('phone_number')->nullable();
                $table->string('location')->nullable();
                $table->string('profile_picture')->nullable();
                $table->timestamps();
            });
            return;
        }

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
