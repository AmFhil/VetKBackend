<?php

namespace App\Services;

use App\Models\MerchantProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackDedicatedAccountService
{
    protected string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
    }

    public function createDedicatedAccount(MerchantProfile $merchant): array
    {
        $response = Http::withToken($this->secretKey)
            ->post('https://api.paystack.co/dedicated_account', [
                'email' => $merchant->user->email,
                'business_name' => `$merchant->user->first_name $merchant->user->last_name`,
                'phone' => $merchant->user->phone_num ?? '',
                'country' => 'NG',
                'preferred_banks' => ['wema', 'titan'], // fastest assignment
                'metadata' => [
                    'merchant_id' => $merchant->id,
                    'user_id' => $merchant->user->id,
                ],
            ]);

        if ($response->failed()) {
            Log::error('Paystack Dedicated Account creation failed', [
                'response' => $response->json(),
                'merchant_id' => $merchant->id,
            ]);
            throw new \Exception('Unable to create Paystack account');
        }

        $data = $response->json('data');

        $merchant->update([
            'paystack_dedicated_account_id' => $data['dedicated_account_id'],
            'paystack_recipient_code' => $data['recipient_code'],
            'dedicated_account_number' => $data['account_number'],
            'dedicated_bank' => $data['bank']['name'],
            'paystack_connected' => true, // recipient_code exists = ready to receive money
            'response_data' =>$data,
        ]);

        // Paystack will email the merchant to log in and add their real bank
        return [
            'account_number' => $data['account_number'],
            'bank' => $data['bank']['name'],
            'recipient_code' => $data['recipient_code'],
        ];
    }
}
