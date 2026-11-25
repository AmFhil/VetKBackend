<?php

namespace App\Http\Controllers;

use App\Models\AdsPromotionReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AdsPromotionReceiptController extends Controller
{
    //
     /**
     * Get all receipts for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = (int) $request->get('per_page', 20);
        $cacheKey = "user_{$user->id}_ads_promotion_receipts";

        $receipts = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($user, $perPage) {
            AdsPromotionReceipt::with(['adsPromotion'])
                ->where('user_id', $user->id)
                ->orderByDesc('paid_at')
                ->paginate($perPage);
        });

        return response()->json([
            'success' => true,
            'receipts' => $receipts,
        ], 200);
    }

}
