<?php

namespace App\Http\Controllers;

use App\Models\CustomerSupport;
use App\Notifications\CustomerSupportNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

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

        $vetkonectSupportEmail = env('Vetkonect_email');
        // send notification
        Notification::route('mail', $vetkonectSupportEmail)
            ->notify(new CustomerSupportNotification($user->first_name, $user->last_name, $user->email, $request->category, $request->complain));

        return response()->json(['message' => 'complain received, will get back to you shortly.'], 200);
    }
}
