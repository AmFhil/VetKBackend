<?php

namespace App\Services;

use App\Models\MerchantPayout;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackTransferService
{
    protected string $secret;
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        $this->secret = config('services.paystack.secret_key');
    }

    public function transferToMerchant(MerchantPayout $payout): array
    {
        $subaccountCode = $payout->metadata['paystack_subaccount'] ?? null;
        if (! $subaccountCode) {
            throw new \Exception('Missing subaccount code for merchant payout');
        }

        $reference = 'mpayout_' . $payout->id . '_' . time();

        $payload = [
            'source' => 'balance',
            'amount' => intval($payout->amount * 100), // convert to cents
            'currency' => $payout->currency ?? 'USD',
            'recipient' => $subaccountCode, // Paystack subaccount code
            'reason' => 'Payout for Order #' . $payout->order_id,
            'reference' => $reference,
        ];

        $response = Http::withToken($this->secret)
            ->post('https://api.paystack.co/transfer', $payload);

        if (!$response->successful()) {
            Log::error('Paystack transfer failed', [
                'payout_id' => $payout->id,
                'response' => $response->json(),
            ]);
            throw new \Exception('Failed to initiate Paystack transfer');
        }

        return [
            'reference' => $reference,
            'response' => $response->json(),
        ];
    }
}
