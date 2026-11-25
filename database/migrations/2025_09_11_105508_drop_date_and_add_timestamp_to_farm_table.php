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
        if (!Schema::hasTable('farm')) {
            Schema::create('farm', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('farm_name')->nullable();
                $table->string('farm_id')->nullable();
                $table->string('location')->nullable();
                $table->integer('no_of_worker')->nullable();
                $table->string('livestock_type')->nullable();
                $table->integer('no_of_livestock')->nullable();
                $table->string('sex')->nullable();
                $table->string('age')->nullable();
                $table->string('picture')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
            return;
        }

        Schema::table('farm', function (Blueprint $table) {
            //
            $table->dropColumn('date');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farm', function (Blueprint $table) {
            //
            $table->dropColumn(['created_at', 'updated_at']);

            $table->date('date');
        });
    }
};
