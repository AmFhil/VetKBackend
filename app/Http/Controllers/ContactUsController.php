<?php

namespace App\Http\Controllers;

use App\Models\ContactUs;
use App\Notifications\ContactUsNotification;
use App\Notifications\UserContactUsNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ContactUsController extends Controller
{
    //
    public function addContactUs(Request $request){
        $request->validate([
            'email' => 'required|email',
            'content' => 'required|string|max:500'
        ]);

        ContactUs::create([
            'email' => $request->email,
            'content' => $request->content
        ]);

        // send email to admin
        $vetkonectAdminEmail = env('Vetkonect_email');
        //use notification
        Notification::route('mail', $vetkonectAdminEmail)
            ->notify(new ContactUsNotification($request->email, $request->content));

        // send mail to user mail
        Notification::route('mail', $request->email)
            ->notify(new UserContactUsNotification($request->email, $request->content));

        return response()->json(['message' => 'Mail Sent successfully'], 200);
    }
}
