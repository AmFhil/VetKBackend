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
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();

            // user or admin joining
            $table->morphs('member');  // member_id, member_type

            // target: blog or forum
            $table->morphs('joinable'); // joinable_id, joinable_type

            $table->timestamps();

            $table->unique(['member_id', 'member_type', 'joinable_id', 'joinable_type'], 'unique_membership');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
