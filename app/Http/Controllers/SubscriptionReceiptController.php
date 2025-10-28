<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SubscriptionReceiptController extends Controller
{
    /**
     * Get all receipts for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = (int) $request->get('per_page', 20);
        $cacheKey = "user_{$user->id}_receipts";

        $receipts = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($user, $perPage) {
            SubscriptionReceipt::with(['subscription', 'userSubscription'])
                ->where('user_id', $user->id)
                ->orderByDesc('paid_at')
                ->paginate($perPage);
        });

        return response()->json([
            'success' => true,
            'receipts' => $receipts,
        ], 200);
    }

    /**
     * Admin-only endpoint: Get all receipts (optionally filtered by user, plan, or date).
     */
    public function all(Request $request)
    {
        //$this->authorize('viewAny', SubscriptionReceipt::class); // optional policy check

        $query = SubscriptionReceipt::with(['user', 'subscription', 'userSubscription'])
            ->orderByDesc('paid_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->subscription_id);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('paid_at', [
                $request->date_from,
                $request->date_to,
            ]);
        }

        $perPage = (int) $request->get('per_page', 50);

        return response()->json([
            'success' => true,
            'receipts' => $query->paginate($perPage),
        ], 200);
    }
}
