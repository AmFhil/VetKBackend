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
        $recipientCode = $payout->merchant->paystack_recipient_code; // from dedicated account

        if (!$recipientCode) {
            throw new \Exception('Merchant has not completed Paystack setup');
        }

        $reference = 'vetkonect_payout_' . $payout->id . '_' . now()->timestamp;

        $response = Http::withToken($this->secret)
            ->post('https://api.paystack.co/transfer', [
                'source' => 'balance',
                'amount' => intval($payout->amount * 100), // kobo
                'recipient' => $recipientCode,
                'reason' => "Payout for Order #{$payout->order_id}, Name: {$payout->merchant->first_name} {$payout->merchant->last_name}",
                'reference' => $reference,
            ]);

        if ($response->failed()) {
            Log::error('Payout failed', $response->json());
            throw new \Exception('Transfer failed: ' . ($response->json('message') ?? 'Unknown error'));
        }

        return $response->json();
    }
}
