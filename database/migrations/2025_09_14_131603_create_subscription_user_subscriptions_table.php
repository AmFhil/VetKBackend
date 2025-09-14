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
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users_registration')->onDelete('cascade');

            $table->unsignedBigInteger('subscription_id'); // link to subscription plan table
            $table->string('subscription_title');
            $table->decimal('price', 10, 2);
            $table->string('currency')->default('USD');
            $table->enum('profile_approval', ['No Approval Badge', 'Approved Badge']);
            $table->integer('Case_Record');
            $table->boolean('contact_info')->default(false);
            $table->boolean('feed_calculator')->default(true);
            $table->boolean('disease_predictor')->default(true);
            $table->json('store');
            $table->boolean('customer_support')->default(false);

            $table->date('start_date');
            $table->date('end_date');

            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
