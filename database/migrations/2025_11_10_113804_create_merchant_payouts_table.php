<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('merchant_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('users_registration')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->string('paystack_transfer_code')->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('USD');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('metadata')->nullable();
            $table->json('response_data')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_payouts');
    }
};
