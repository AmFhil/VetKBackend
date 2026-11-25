<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('store')) {
            Schema::create('store', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('name')->nullable();
                $table->string('store_name')->nullable();
                $table->string('address')->nullable();
                $table->string('phone')->nullable();
                $table->string('picture')->nullable();
                $table->boolean('availability')->default(true);
                $table->string('longitude')->nullable();
                $table->string('latitude')->nullable();
                $table->timestamps();
            });
            return;
        }

        //
        Schema::table('store', function(Blueprint $table){
            // truncate exiting data
            DB::table('store')->truncate();

            $table->dropColumn(['availability', 'date']);

            $table->boolean('availability')->default(true);
            $table->string('longitude');
            $table->string('latitude');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('store', function(Blueprint $table){
            $table->dropColumn(['longitude','latitude','created_at', 'updated_at']);

            $table->string('date');
        });
    }
};
