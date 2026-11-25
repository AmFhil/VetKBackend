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
        Schema::table('merchant_profiles', function (Blueprint $table) {
            //
            $table->dropColumn(['integration', 'verified']);
            $table->string('paystack_dedicated_account_id')->nullable()->after('paystack_subaccount_code')->index();
            $table->string('paystack_recipient_code')->nullable()->unique()->after('paystack_dedicated_account_id');
            $table->string('dedicated_account_number')->nullable()->after('paystack_recipient_code');
            $table->string('dedicated_bank')->nullable()->after('dedicated_account_number');
            $table->boolean('paystack_connected')->default(false)->after('dedicated_bank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_profiles', function (Blueprint $table) {
            //
            $table->string('integration')->nullable()->after('paystack_subaccount_code');
            $table->boolean('verified')->default(false)->after('integration');
            $table->dropColumn([
                'paystack_dedicated_account_id',
                'paystack_recipient_code',
                'dedicated_account_number',
                'dedicated_bank',
                'paystack_connected',
            ]);
        });
    }
};
