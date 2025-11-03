<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Products;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class PaystackOrderController extends Controller
{
    public function initialize(Request $request)
    {
        $request->validate([
            'merchant_user_id' => 'required|exists:users_registration,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $product = Products::findOrFail($request->product_id);

        if (!$product->availability || $product->available_unit < $request->quantity) {
            return response()->json(['success' => false, 'message' => 'Product not available or insufficient stock'], 400);
        }

        $totalAmount = $product->price * $request->quantity;
        $amountKobo = intval($totalAmount * 100);

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email,
                'amount' => $amountKobo,
                'currency' => 'USD',
                'callback_url' => route('order.verify'),
                'metadata' => [
                    'type' => 'order',
                    'buyer_user_id' => $user->id,
                    'merchant_user_id' => $request->merchant_user_id,
                    'product_id' => $product->id,
                    'quantity' => $request->quantity,
                    'price' => $product->price,
                ],
            ]);

        $data = $response->json();

        if (!$response->successful() || !$data['status']) {
            return response()->json([
                'success' => false,
                'message' => $data['message'] ?? 'Failed to initialize payment',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'authorization_url' => $data['data']['authorization_url'],
            'reference' => $data['data']['reference'],
        ]);
    }

    public function verify(Request $request, OrderService $orderServices)
    {
        $reference = $request->query('reference');

        if (!$reference) {
            return response()->json([
                'success' => false,
                'message' => 'Reference not provided'
            ], 400);
        }

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        $data = $response->json();

        if (!$response->successful() || !$data['status']) {
            return response()->json(['success' => false, 'message' => $data['message'] ?? 'Failed to verify payment'], 400);
        }

        $payment = $data['data'];
        if ($payment['status'] !== 'success') {
            return response()->json(['success' => false, 'message' => 'Payment not successful'], 400);
        }

        // ✅ Delegate order creation
        $order = $orderServices->createOrderFromPayment($payment);

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully after payment',
            'order' => $order->load('items', 'payment')
        ], 200);
    }
}
