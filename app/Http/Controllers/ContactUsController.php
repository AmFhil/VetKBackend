<?php

namespace App\Http\Controllers;

use App\Models\ContactUs;
use Illuminate\Http\Request;

class ContactUsController extends Controller
{
    //
    public function addContactUs(Request $request){
        $request->validate([
            'email' => 'required|email|unique:newsletter,email,',
            'content' => 'required|string|max:500'
        ]);

        ContactUs::create([
            'email' => $request->email,
            'content' => $request->content
        ]);

        return response()->json(['message' => 'Mail Sent successfully'], 200);
    }
}
