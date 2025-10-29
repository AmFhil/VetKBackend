<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ads_promotion_receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ads_promotion_id')
                ->constrained('ads_promotions')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained('users_registration')
                ->onDelete('cascade');

            $table->foreignId('promotion_plan_id')
                ->constrained('promotion_plan')
                ->onDelete('cascade');

            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->unique();
            $table->string('reference_number')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->json('promotion_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_promotion_receipts');
    }
};
