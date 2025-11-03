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
        Schema::table('payments', function (Blueprint $table) {
            //
            $table->string('reference_number')->after('transaction_id')->nullable();
            $table->json('gateway_response')->after('meta')->nullable();
            $table->string('refund_reference')->after('reference_number')->nullable();
            $table->json('refund_details')->after('refund_reference')->nullable();
            $table->enum('status', ['pending', 'successful', 'failed', 'refunded'])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            //
            $table->dropColumn(['reference_number', 'gateway_response', 'refund_reference', 'refund_details']);
        });
    }
};
