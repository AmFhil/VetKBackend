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
        if (!Schema::hasColumn('clinic', 'user_id')) {
            Schema::table('clinic', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->after('id');
                $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');
            });
        }
        if (!Schema::hasColumn('clinic', 'disabled')) {
            Schema::table('clinic', function (Blueprint $table) {
                $table->boolean('disabled')->default(false);
            });
        }

        Schema::table('farm', function (Blueprint $table) {
           $table->boolean('disabled')->default(false);
        });

        Schema::table('cases', function (Blueprint $table) {
           $table->boolean('disabled')->default(false);
        });

        Schema::table('pet', function (Blueprint $table) {
           $table->boolean('disabled')->default(false);
        });

        Schema::table('products', function (Blueprint $table) {
           $table->boolean('disabled')->default(false);
        });

        Schema::table('store', function (Blueprint $table) {
            $table->boolean('disabled')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinic', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropColumn('disabled');
            $table->string('user_id'); //
        });

        Schema::table('farm', function (Blueprint $table) {
            //
            $table->dropColumn('disabled');
        });

        Schema::table('cases', function (Blueprint $table) {
            //
            $table->dropColumn('disabled');
        });

        Schema::table('pet', function (Blueprint $table) {
            //
            $table->dropColumn('disabled');
        });

        Schema::table('products', function (Blueprint $table) {
            //
            $table->dropColumn('disabled');
        });

        Schema::table('store', function (Blueprint $table) {
            //
            $table->dropColumn('disabled');
        });
    }
};
