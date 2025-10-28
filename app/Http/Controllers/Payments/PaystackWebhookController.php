<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaystackWebhookController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Handle incoming Paystack webhooks.
     */
    public function handle(Request $request)
    {
        $secret = config('services.paystack.secret'); // make sure it's in config/services.php

        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        // Verify request authenticity
        if (!$signature || !hash_equals(hash_hmac('sha512', $payload, $secret), $signature)) {
            Log::warning('Paystack webhook: Invalid signature attempt', [
                'ip' => $request->ip(),
            ]);
            return response('Invalid signature', Response::HTTP_UNAUTHORIZED);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        Log::info("Paystack webhook received: {$event}", [
            'reference' => $data['reference'] ?? null,
            'transaction_id' => $data['id'] ?? null,
        ]);

        try {
            switch ($event) {
                /**
                 * Subscription Payment Success
                 * This triggers after successful payment verification.
                 */
                case 'charge.success':
                    $this->handleSubscriptionPayment($data);
                    break;

                /**
                 * (Optional) Handle refund or failed charge events later
                 */
                case 'charge.failed':
                    Log::warning('Paystack charge failed', [
                        'reference' => $data['reference'],
                        'email' => $data['customer']['email'] ?? null,
                    ]);
                    break;

                default:
                    Log::info("Paystack webhook ignored: {$event}");
                    break;
            }

            return response('Webhook processed', Response::HTTP_OK);

        } catch (\Throwable $e) {
            Log::error('Paystack webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response('Error processing webhook', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Handle successful Paystack payment for a subscription.
     */
    protected function handleSubscriptionPayment(array $data): void
    {
        // Ensure metadata exists
        $metadata = $data['metadata'] ?? [];
        if (!isset($metadata['subscription_id'], $metadata['user_id'])) {
            Log::warning('Paystack webhook missing metadata', ['data' => $data]);
            return;
        }

        // Normalize amount to USD (Paystack sends in kobo/cents)
        $amount = isset($data['amount']) ? $data['amount'] / 100 : 0;

        // Delegate to SubscriptionService (idempotent)
        $this->subscriptionService->subscribe(
            $metadata['subscription_id'],
            'paystack',
            [
                'transaction_id' => $data['id'],
                'reference_number' => $data['reference'],
                'amount' => $amount,
                'currency' => $data['currency'] ?? 'USD',
                'user_id' => $metadata['user_id'],
                'metadata' => $metadata,
            ]
        );

        Log::info('Subscription payment processed successfully via webhook', [
            'reference' => $data['reference'],
            'user_id' => $metadata['user_id'],
        ]);
    }
}
