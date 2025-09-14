<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_subscriptions');

        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');

            $table->unsignedBigInteger('subscription_id'); // fixed column name
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade');

            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
