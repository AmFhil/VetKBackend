<?php

namespace App\Http\Controllers;

use App\Events\DirectMessageSent;
use App\Models\DirectMessage;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\OrderConfirmation;
use App\Models\OrderItem;
use App\Models\Products;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function createOrder(Request $request)
    {
        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $request->validate([
            'merchant_user_id' => 'required|exists:users_registration,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            //'payment_method' => 'required|string|in:paypal,paystack,transfer',
        ]);

        try {
            return DB::transaction(function () use ($request, $user) {
                $product = Products::with('user:id,first_name,last_name')->findOrFail($request->product_id);
                $merchant = User::findOrFail($request->merchant_user_id);

                if ($product->product_type === 1) {
                    return response()->json(['success' => false, 'message' => 'forbidden'], 400);
                }

                if (!$product->availability || $product->available_unit < $request->quantity) {
                    return response()->json(['message' => 'Product not available or insufficient stock.'], 400);
                }

                $price = $product->price;
                $totalAmount = $price * $request->quantity;

                // $order = Order::create([
                //     'merchant_user_id' => $request->merchant_user_id,
                //     'buyer_user_id' => $user->id,
                //     'product_id' => $product->id,
                //     'tracking_number' => strtoupper(uniqid('TRK')),
                //     'payment_method' => $request->payment_method,
                //     'payment_status' => 'pending',
                //     'status' => 'active',
                //     'total_amount' => $totalAmount,
                //     'price' => $price,
                //     'quantity' => $request->quantity,
                //     'timeline' => [
                //         'created_at' => now()->toDateTimeString(),
                //         'events' => ['Order created'],
                //     ],
                // ]);

                // order item snapshot
                // OrderItem::create([
                //     'order_id' => $order->id,
                //     'product_id' => $product->id,
                //     'product_name' => $product->product_name,
                //     'price' => $price,
                //     'quantity' => $request->quantity,
                //     'subtotal' => $totalAmount,
                //     'product_snapshot' => $product->toArray(),
                // ]);

                // initialize and verify payment
                //$payment = PaymentService::initialize($order, $request->payment_method);
                //PaymentService::verify($payment);

                // confirmation record
                //OrderConfirmation::create(['order_id' => $order->id]);

                // reduce stock
                $product->decrement('available_unit', $request->quantity);

                // send message to merchant
                $message = DirectMessage::create([
                    'sender_id' => $user->id,
                    'receiver_id' => $request->merchant_user_id,
                    'type' => 'order',
                    'content' => "{$user->first_name} {$user->last_name} just made an order for {$product->product_name}",
                    'meta' => [
                        // 'order_id' => $order->id,
                        'product_images' => $product->images,
                        // 'view_url'       => route('order.show', $product->id),
                        // 'cancel_url'     => route('order.cancel', $product->id),
                    ]
                ]);

                event(new DirectMessageSent($message));

                $user->logActivity("order_created", "You placed an order for {$product->product_name}", "{$product->user->first_name} {$product->user->last_name} has receive the order and will get in touch with you.", ["product_id" => $product->product_id]);
                $merchant->sendNotification(
                    'order_created',
                    'New Order Received',
                    "{$user->first_name} {$user->last_name} just placed an order for {$product->product_name}.",
                    []
                );

                return response()->json([
                    'message' => 'Order created successfully.',
                ], 201);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while creating the order.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getBuyerOrders()
    {
        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $cacheKey = "buyer_orders_{$user->id}";

        $orders = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user) {
            return Order::where('buyer_user_id', $user->id)
                ->with('items', 'confirmation', 'complaints')
                ->latest()
                ->paginate(10);
        });

        return response()->json(['orders' => $orders], 200);
    }

    public function getMerchantOrders()
    {
        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $cacheKey = "merchant_orders_{$user->id}";

        $orders = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user) {
            return Order::where('merchant_user_id', $user->id)
                ->with('items', 'confirmation', 'complaints')
                ->latest()
                ->paginate(10);
        });

        return response()->json(['orders' => $orders], 200);
    }

    public function confirmOrder(Request $request, $orderId)
    {
        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $order = Order::with('confirmation')->find($orderId);
        if (!$order) return response()->json(['message' => 'Order not found.'], 404);

        $confirmation = $order->confirmation;

        $isMerchant = $user->id === $order->merchant_user_id;
        $isBuyer = $user->id === $order->buyer_user_id;

        if (!$isMerchant || !$isBuyer) {
            return response()->json(['message' => 'You are not authorized for this order.'], 403);
        }

        if ($isMerchant) {
            $confirmation->update([
                'merchant_confirmed' => true,
                'merchant_confirmed_at' => now(),
            ]);

            // Notify the buyer
            $order->buyer->sendNotification(
                'order_update',
                'Merchant Confirmed Delivery',
                "Merchant has confirmed your order #{$order->tracking_number}.",
                ['order_id' => $order->id]
            );
        } else {
            $confirmation->update([
                'buyer_confirmed' => true,
                'buyer_confirmed_at' => now(),
            ]);

            // Notify the merchant
            $order->merchant->sendNotification(
                'order_update',
                'Buyer Confirmed Receipt',
                "Buyer has confirmed receipt for order #{$order->tracking_number}.",
                ['order_id' => $order->id]
            );
        }

        // log activity
        $user->logActivity('order_confirmation', 'Order confirmation updated', "You confirmed order #{$order->tracking_number}");

        return response()->json([
            'message' => 'Confirmation updated successfully.',
            'confirmation' => $confirmation,
        ], 200);
    }

    public function fileComplaint(Request $request, $orderId)
    {
        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $request->validate(['reason' => 'required|string|max:1000']);

        $order = Order::find($orderId);
        if (!$order) return response()->json(['message' => 'Order not found.'], 404);

        $complaint = OrderComplaint::create([
            'order_id' => $orderId,
            'user_id' => $user->id,
            'reason' => $request->reason,
            'status' => 'open',
        ]);

        // clear related cache
        Cache::forget("buyer_orders_{$order->buyer_user_id}");
        Cache::forget("merchant_orders_{$order->merchant_user_id}");
        Cache::forget("order_details_{$order->id}");

        $user->logActivity('order_complaint', 'Complaint filed', "Complaint filed for order #{$order->tracking_number}");
        $target = $user->id === $order->buyer_user_id ? $order->merchant : $order->buyer;
        $target->sendNotification('order_complaint', 'Complaint Filed', "A complaint has been filed for order #{$order->tracking_number}.", [
            'order_id' => $order->id,
        ]);

        return response()->json([
            'message' => 'Complaint filed successfully.',
            'complaint' => $complaint,
        ], 201);
    }

    public function cancelOrder($orderId)
    {
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = Order::with('payment', 'merchant')
            ->where('id', $orderId)
            ->where('buyer_user_id', $user->id)
            ->where('status', 'completed')
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found or cannot be cancelled.'], 404);
        }

        // Check time limit (24 hours)
        $hoursPassed = $order->created_at->diffInHours(now());
        if ($hoursPassed >= 24) {
            return response()->json([
                'message' => 'The 24-hour cancellation window has expired for this order.'
            ], 401);
        }

        // If paid via Paystack, refund before cancel
        if ($order->payment_status === 'paid' && $order->payment_method === 'paystack') {
            try {
                $refundResponse = Http::withToken(config('services.paystack.secret_key'))
                    ->post('https://api.paystack.co/refund', [
                        'transaction' => $order->payment->reference_number, // from payment record
                        'amount' => intval($order->total_amount * 100), // in kobo
                        'currency' => 'USD',
                        'customer_note' => "Refund for cancelled order #{$order->tracking_number}"
                    ]);

                $refundData = $refundResponse->json();

                if (!$refundResponse->successful() || !($refundData['status'] ?? false)) {
                    return response()->json([
                        'message' => 'Order cancellation failed. Refund could not be processed.',
                        'error' => $refundData['message']
                    ], 400);
                    Log::info([
                        'message' => 'Order cancellation failed. Refund could not be processed.',
                        'paystack_response' => $refundData
                    ]);
                }

                // Update payment record
                $order->payment->update([
                    'status' => 'refunded',
                    'refund_reference' => $refundData['data']['reference'] ?? null,
                    'refund_details' => $refundData['data'] ?? [],
                ]);

                // Update order status
                $order->update(['status' => 'cancelled', 'payment_status' => 'refunded']);

                // Log & notify
                $user->logActivity(
                    'order_refunded',
                    'Order Cancelled & Refunded',
                    "You cancelled and received a refund for order #{$order->tracking_number}"
                );

                $order->merchant->sendNotification(
                    'order_cancelled',
                    'Order Cancelled & Refunded',
                    "Buyer cancelled and was refunded for order #{$order->tracking_number}.",
                    ['order_id' => $order->id]
                );

                return response()->json([
                    'message' => 'Order cancelled and refunded successfully.',
                    'refund_details' => $refundData['data'] ?? null,
                ], 200);
            } catch (\Throwable $e) {
                Log::error('Paystack refund failed', ['error' => $e->getMessage()]);
                return response()->json([
                    'message' => 'Refund failed, please contact support.',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        // If not paid through Paystack, just cancel
        $order->update(['status' => 'cancelled']);
        $user->logActivity('order_cancelled', 'Order Cancelled', "You cancelled order #{$order->tracking_number}");

        $order->merchant->sendNotification(
            'order_cancelled',
            'Order Cancelled',
            "Buyer cancelled order #{$order->tracking_number}.",
            ['order_id' => $order->id]
        );

        return response()->json(['message' => 'Order cancelled successfully.'], 200);
    }


    public function getOrderDetails($orderId)
    {
        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $cacheKey = "order_details_{$orderId}";

        $order = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($user, $orderId) {
            return Order::with(['items', 'confirmation', 'complaints'])
                ->where(function ($query) use ($user) {
                    $query->where('merchant_user_id', $user->id)
                        ->orWhere('buyer_user_id', $user->id);
                })
                ->find($orderId);
        });

        if (!$order) return response()->json(['message' => 'Order not found.'], 404);

        return response()->json(['order' => $order], 200);
    }
}
