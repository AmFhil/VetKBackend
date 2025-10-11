<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_user_id')->constrained('users_registration')->cascadeOnDelete();
            $table->foreignId('buyer_user_id')->constrained('users_registration')->cascadeOnDelete();
            $table->string('tracking_number')->nullable()->index();
            $table->string('payment_method');
            $table->string('payment_status');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->json('timeline')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('product_name');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('subtotal', 10, 2);
            $table->json('product_snapshot')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('order_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->boolean('merchant_confirmed')->default(false);
            $table->boolean('buyer_confirmed')->default(false);
            $table->timestamp('merchant_confirmed_at')->nullable();
            $table->timestamp('buyer_confirmed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('order_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users_registration')->cascadeOnDelete();
            $table->text('reason');
            $table->enum('status', ['open', 'in_review', 'resolved', 'dismissed'])->default('open');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_complaints');
        Schema::dropIfExists('order_confirmations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
