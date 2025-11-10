<?php

namespace App\Services;

use App\Models\MerchantProfile;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackSubaccountService
{
    /**
     * Create or update a merchant subaccount on Paystack.
     */
    public function ensureMerchantSubaccount(User $merchant): MerchantProfile
    {
        // Check if merchant already has a subaccount
        $profile = MerchantProfile::where('user_id', $merchant->id)->first();

        if ($profile && $profile->paystack_subaccount_code) {
            return $profile;
        }

        // Create subaccount on Paystack
        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/subaccount', [
                'business_name' => "{$merchant->first_name} {$merchant->last_name}",
                'settlement_bank' => 'placeholder', // user will fill this in Paystack UI
                'account_number' => '0000000000', // Paystack will ask in UI
                'percentage_charge' => 0,
                'description' => 'VetKonect merchant subaccount',
                'currency' => 'USD',
            ]);

        if (!$response->successful()) {
            Log::error('Failed to create Paystack subaccount', [
                'merchant_id' => $merchant->id,
                'response' => $response->json(),
            ]);
            throw new \Exception('Unable to create subaccount on Paystack.');
        }

        $data = $response->json('data');

        // Save merchant profile with subaccount info
        return MerchantProfile::updateOrCreate(
            ['user_id' => $merchant->id],
            [
                'paystack_subaccount_code' => $data['subaccount_code'],
                'integration' => $data['integration'],
                'verified' => true,
                'response_data' => $data,
            ]
        );
    }
}
