<?php

namespace App\Http\Controllers;

use App\Models\CustomerSupport;
use Illuminate\Http\Request;

class CustomerSupportController extends Controller
{
    //
    public function DropAComplain(Request $request)
    {
        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $request->validate([
            'category' => 'required|string',
            'complain' => 'required|string|max:1000'
        ]);

        CustomerSupport::create([
            'user_id' => $user->id,
            'category' => $request->category,
            'complain' => $request->complain
        ]);

        return response()->json(['message' => 'complain received, will get back to you shortly.'], 200);
    }
}
