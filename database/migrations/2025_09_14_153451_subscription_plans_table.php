<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscription_title');
            $table->string('subscription_code')->unique(); // sp2295, sp3154, etc.
            $table->string('currency')->default('USD');
            $table->decimal('price', 10, 2);
            $table->string('vat')->nullable();

            $table->enum('date_option', ['Days', 'Months']);
            $table->integer('duration'); // 30 days, 3 months, etc.

            // Features
            $table->integer('case_record')->nullable(); // Freemium = 2, Monthly = 20, Quarterly = 50, Yearly = unlimited (NULL or -1 for unlimited)
            $table->enum('profile_approval', ['No Approval Badge', 'Approved Badge'])->default('No Approval Badge');
            $table->boolean('contact_info')->default(false);
            $table->boolean('direct_message')->default(false);
            $table->boolean('feed_calculator')->default(false);
            $table->boolean('disease_predictor')->default(false);
            $table->integer('store')->nullable(); // number of products in store (null/-1 for unlimited)
            $table->integer('no_of_products')->nullable();
            $table->boolean('customer_support')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
