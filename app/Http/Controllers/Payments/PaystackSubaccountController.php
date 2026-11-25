<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\MerchantProfile;
use App\Services\PaystackSubaccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaystackSubaccountController extends Controller
{
    protected PaystackSubaccountService $service;

    public function __construct(PaystackSubaccountService $service)
    {
        $this->service = $service;
    }

    /**
     * Step 1: Merchant clicks "Connect Paystack"
     */
    public function connect(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        // user active role must be vendor
        if ($user->activeRole->name !== 'vendor') return response()->json(['message' => 'Only merchants can connect account'], 403);

        $merchant = MerchantProfile::firstOrCreate(
            ['user_id' => $user->id]
        );

        $response = $this->service->createSubaccount($merchant);

        return response()->json([
            'success' => true,
            'message' => 'Redirect to Paystack onboarding to complete setup.',
            'onboarding_url' => $response['onboarding_url'] ?? "https://dashboard.paystack.com/#/subaccount-onboard/{$response['subaccount_code']}",
        ]);
    }

    /**
     * Step 2: Paystack redirects back to this endpoint after onboarding
     */
    public function callback(Request $request)
    {
        Log::info('Paystack subaccount callback received', $request->all());

        $subaccountCode = $request->query('subaccount_code');
        $userId = $request->query('user_id');

        if (!$subaccountCode || !$userId) {
            return response()->json(['success' => false, 'message' => 'Invalid callback data'], 400);
        }

        $merchant = MerchantProfile::where('user_id', $userId)->first();
        if (!$merchant) {
            return response()->json(['success' => false, 'message' => 'Merchant not found'], 404);
        }

        $merchant->update(['paystack_subaccount_code' => $subaccountCode]);

        // need user url settings
        $url = env('FRONTEND_URL');
        return redirect()->away($url . '/merchant/settings?connected=paystack');
    }
}
