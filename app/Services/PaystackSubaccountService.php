<?php

namespace App\Services;

use App\Models\MerchantProfile;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackSubaccountService
{
    protected string $baseUrl;
    protected string $secretKey;

    public function __construct()
    {
        $this->baseUrl = 'https://api.paystack.co';
        $this->secretKey = config('services.paystack.secret_key');
    }

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

    public function createSubaccount(MerchantProfile $merchant): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/subaccount", [
                'business_name' => "{$merchant->user->first_name} {$merchant->user->last_name}",
                'settlement_bank' => '000', // placeholder
                'account_number' => '0000000000',
                'percentage_charge' => 0,
                'primary_contact_email' => $merchant->user->email,
                'primary_contact_name'  => "{$merchant->user->first_name} {$merchant->user->last_name}",
                'metadata' => [
                    'user_id' => $merchant->user->id,
                    'callback_url' => config('services.paystack.paystack_subacccount_callback_url'),
                ],
            ]);

        if ($response->failed()) {
            Log::error('Paystack Subaccount creation failed', ['response' => $response->json()]);
            throw new \Exception('Failed to create Paystack subaccount');
        }

        $data = $response->json()['data'];
        $merchant->update([
            'paystack_subaccount_code' => $data['subaccount_code'],
            'verified' => true,
            'integration' => $data['integration'],
            'response_data' => $data,
        ]);

        return $data;
    }
}
