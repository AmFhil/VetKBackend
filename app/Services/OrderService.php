<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderConfirmation;
use App\Models\Products;
use App\Models\DirectMessage;
use Illuminate\Support\Facades\DB;
use App\Events\DirectMessageSent;
use App\Models\Payment;

class OrderService
{
    public function createOrderFromPayment(array $paymentData)
    {
        return DB::transaction(function () use ($paymentData) {
            $metadata = $paymentData['metadata'];
            $product = Products::findOrFail($metadata['product_id']);
            $quantity = $metadata['quantity'];
            $totalAmount = $paymentData['amount'] / 100;
            $buyer_user = auth('user')->user() ?? $metadata['buyer_user_id'];
            $merchant_user = $metadata['merchant_user_id'];
            $transaction_id = $paymentData['transaction_id'];
            $reference = $paymentData['reference'];

            // idempotency check
            $existingPayment = Payment::where('transaction_id', $transaction_id)->first();
            if($existingPayment){
               return $existingPayment->order;
            }

            // Create order
            $order = Order::create([
                'merchant_user_id' => $metadata['merchant_user_id'],
                'buyer_user_id' => $metadata['buyer_user_id'],
                'product_id' => $product->id,
                'tracking_number' => strtoupper(uniqid('TRK')),
                'payment_method' => 'paystack',
                'payment_status' => 'paid',
                'status' => 'active',
                'total_amount' => $totalAmount,
                'price' => $metadata['price'],
                'quantity' => $quantity,
                'timeline' => [
                    'created_at' => now()->toDateTimeString(),
                    'events' => ['Order created after payment'],
                ],
            ]);

            // Create order item
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->product_name,
                'price' => $metadata['price'],
                'quantity' => $quantity,
                'subtotal' => $totalAmount,
                'product_snapshot' => $product->toArray(),
            ]);

            // Confirmation record
            OrderConfirmation::create(['order_id' => $order->id]);

            // Reduce stock
            $product->decrement('available_unit', $quantity);

            // payment record
            Payment::create([
                'order_id' => $order->id,
                'method' => 'paystack',
                'amount' => $totalAmount,
                'status' => 'successful',
                'transaction_id' => $transaction_id,
                'reference_number' => $reference,
                'meta' => $metadata,
                'gateway_response' => $paymentData
            ]);

            // Notify merchant
            $message = DirectMessage::create([
                'sender_id' => $metadata['buyer_user_id'],
                'receiver_id' => $metadata['merchant_user_id'],
                'type' => 'order',
                'content' => "{$buyer_user->first_name} {$buyer_user->last_name} just made an order for {$product->product_name}",
                'meta' => [
                    'order_id' => $order->id,
                    'product_images' => $product->images,
                    'view_url'       => route('order.show', $product->id),
                    'cancel_url'     => route('order.cancel', $product->id),
                ]
            ]);

            event(new DirectMessageSent($message));

            $buyer_user->logActivity('order_created', 'You placed an order', "Order #{$order->tracking_number} for {$product->product_name}");
            $order->merchant->sendNotification(
                'order_created',
                'New Order Received',
                "{$buyer_user->first_name} {$buyer_user->last_name} just placed an order for {$product->product_name}.",
                ['order_id' => $order->id]
            );

            return $order;
        });
    }
}
