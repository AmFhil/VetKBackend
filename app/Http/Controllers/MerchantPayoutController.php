<?php

namespace App\Http\Controllers;

use App\Models\MerchantPayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MerchantPayoutController extends Controller
{
    //
     public function index()
    {
        $merchantId = Auth::id();
        $payouts = MerchantPayout::where('merchant_id', $merchantId)->latest()->paginate(10);

        return response()->json(['payouts' => $payouts]);
    }
}
