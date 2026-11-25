<?php

namespace App\Http\Controllers;

use App\Models\Newsletter;
use App\Notifications\NewsLetterNotification;
use App\Notifications\UserNewsLetterNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class NewsletterController extends Controller
{
    //
    public function addNewsletter(Request $request){
        $request->validate([
            'email' => 'required|email|unique:newsletter,email,',
            // 'ip' => 'required|ip',
        ]);

        $newsletter = Newsletter::create([
            'email' => $request->email
        ]);

        // send mail to admin
        $vetkonectAdminEmail = env('Vetkonect_email');
        Notification::route('mail', $vetkonectAdminEmail)
            ->notify(new NewsLetterNotification($request->email));

        // send mail to user
        Notification::route('mail', $request->email)
            ->notify(new UserNewsLetterNotification($request->email));

        return response()->json(['message' => 'Email submitted successfully'], 200);
    }
}
