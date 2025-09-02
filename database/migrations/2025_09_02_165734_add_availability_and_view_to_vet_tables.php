<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veterinary_doctor', function (Blueprint $table) {
            $table->boolean('availability')->default(false)->after('is_approved');
            $table->unsignedBigInteger('view')->default(0)->after('availability');
        });

        Schema::table('veterinary_clinic', function (Blueprint $table) {
            $table->boolean('availability')->default(false)->after('is_approved');
            $table->unsignedBigInteger('view')->default(0)->after('availability');
        });

        Schema::table('veterinary_paraprofessional', function (Blueprint $table) {
            $table->boolean('availability')->default(false)->after('is_approved');
            $table->unsignedBigInteger('view')->default(0)->after('availability');
        });
    }

    public function down(): void
    {
        Schema::table('veterinary_doctor', function (Blueprint $table) {
            $table->dropColumn(['availability', 'view']);
        });

        Schema::table('veterinary_clinic', function (Blueprint $table) {
            $table->dropColumn(['availability', 'view']);
        });

        Schema::table('veterinary_paraprofessional', function (Blueprint $table) {
            $table->dropColumn(['availability', 'view']);
        });
    }
};
