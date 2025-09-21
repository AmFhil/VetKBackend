<?php

namespace App\Http\Controllers;

use App\Models\Newsletter;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    //
    public function addNewsletter(Request $request){
        $request->validate([
            'email' => 'required|unique:newsletter,email,',
            // 'ip' => 'required|ip',
        ]);

        $newsletter = Newsletter::create([
            'email' => $request->email
        ]);

        return response()->json(['message' => 'Email submitted successfully'], 200);
    }
}
