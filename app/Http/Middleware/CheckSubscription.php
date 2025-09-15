<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckSubscription
{
    public function handle(Request $request, Closure $next, $feature = null)
    {
        $user = Auth::user();

        if (!$user || !$user->subscription) {
            return response()->json(['message' => 'No subscription found.'], 403);
        }

        $subscription = $user->subscription;

        // expired or cancelled
        if (!$subscription->isActive()) {
            return response()->json(['message' => 'Your subscription has expired. Please upgrade.'], 403);
        }

        // If feature check is passed in route
        if ($feature) {
            $plan = $subscription->subscription;

            switch ($feature) {
                case 'store':
                    // store limit
                    if ($plan->store !== -1) {
                        $storeCount = $user->stores()->count();
                        if ($storeCount >= $plan->store) {
                            return response()->json([
                                'message' => "Your plan allows max {$plan->store} stores."
                            ], 403);
                        }
                    }
                    break;

                case 'product':
                    // product limit per store
                    if ($plan->no_of_products !== -1) {
                        $storeId = $request->input('store_id');
                        if ($storeId) {
                            $productCount = $user->products()->where('store_id', $storeId)->count();
                            if ($productCount >= $plan->no_of_products) {
                                return response()->json([
                                    'message' => "Your plan allows max {$plan->no_of_products} products per store."
                                ], 403);
                            }
                        }
                    }
                    break;

                case 'case_record':
                    if ($plan->case_record !== -1) {
                        $caseCount = $user->cases()->count();
                        if ($caseCount >= $plan->case_record) {
                            return response()->json([
                                'message' => "Your plan allows max {$plan->case_record} case records."
                            ], 403);
                        }
                    }
                    break;

                case 'direct_message':
                    if (!$plan->direct_message) {
                        return response()->json([
                            'message' => "Direct messaging is not included in your plan."
                        ], 403);
                    }
                    break;

                case 'contact_info':
                    if (!$plan->contact_info) {
                        return response()->json([
                            'message' => "Viewing contact info is not included in your plan."
                        ], 403);
                    }
                    break;

                default:
                    // do nothing if no special feature passed
                    break;
            }
        }

        return $next($request);
    }
}
