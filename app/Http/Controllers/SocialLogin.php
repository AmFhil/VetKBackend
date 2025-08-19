<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialLogin extends AuthController
{
    //
    public function toGoogle()
    {
        // logic to redirect to google for authentication
        return Socialite::driver('google')->redirect();
    }
}
