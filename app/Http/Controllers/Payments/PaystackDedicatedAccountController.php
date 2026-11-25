<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\MerchantProfile;
use App\Services\PaystackDedicatedAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaystackDedicatedAccountController extends Controller
{
    //
    protected PaystackDedicatedAccountService $service;

    public function __construct(PaystackDedicatedAccountService $service)
    {
        $this->service = $service;
    }

    public function connect(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->activeRole->name !== 'vendor') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $merchant = MerchantProfile::firstOrCreate(['user_id' => $user->id]);

        // If already connected, just return success
        if ($merchant->paystack_connected) {
            return response()->json([
                'success' => true,
                'message' => 'Already connected to Paystack',
                'connected' => true,
            ]);
        }

        try {
            $result = $this->service->createDedicatedAccount($merchant);

            return response()->json([
                'success' => true,
                'message' => 'Paystack account created. Please check your email to complete setup.',
                'account_number' => $result['account_number'],
                'bank' => $result['bank'],
                'connected' => false, // Will be true after they add bank in dashboard
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect Paystack: ' . $e->getMessage(),
            ], 500);
        }
    }
}
