<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;

class PaymentService
{
    /**
     * Create a new class instance.
     */
    public static function initialize(Order $order, string $method)
    {
        return Payment::create([
            'order_id' => $order->id,
            'method' => $method,
            'amount' => $order->total_amount,
            'status' => 'pending',
        ]);
    }

    public static function verify(Payment $payment)
    {
        $payment->update(['status' => 'successful']);
        $payment->order->update(['payment_status' => 'paid']);

        return $payment;
    }
}
