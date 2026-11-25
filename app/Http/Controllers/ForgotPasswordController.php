<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ForgotPasswordController extends Controller
{
    // method to send reset password link
    public function sendResetLink(Request $request)
    {
        // validate the request
        $request->validate([
            'email' => 'required|email|exists:users_registration,email',
        ]);

        // check if the user email exits
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['error' => 'Email does not seem to exist on our platform'], 404);
        } elseif ($user->deleted_at !== null) {
            return response()->json(['error' => 'Request Failed. Account associated with this email has been deleted'], 404);
        }

        // generate reset token
        $token = app('auth.password.broker')->createToken($user);

        // delete any exiting reset tokens for the user
        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->orWhere('created_at', '<', Carbon::now()->subMinutes(10))
            ->delete();

        // store the reset token
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $token,
                'created_at' => Carbon::now()
            ]
        );

        // generate reset link
        $resetUrl = env('FRONTEND_URL') . '/reset-password/forgot-password?token=' . $token . '&email=' . urlencode($request->email);
        // generate for texting
        // $textUrl = env('TEXT_url') . '/reset-password/forgot-password?token=' .$token . '&email=' . urlencode($request->email);

        // dispatch the mail job to send the reset link
        try {
            $user->notify(new ResetPasswordLink($resetUrl, $user->name, $user->email));
        } catch (\Exception $e) {
            //  fallback to notification route
            Notification::route('mail', $user->email)->notifyNow(new ResetPasswordLink($resetUrl, $user->name, $user->email));
        }

        // return a response
        return response()->json(['message' => 'Reset Password link sent successfully'], 200);
    }

    // method ro reset password
    public function resetPassword(Request $request)
    {
        // validate the request
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users_registration,email',
            'password' => 'required|string|min:8|confirmed'
        ]);

        // validate the token
        $tokenData = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$tokenData) {
            return response()->json(['error' => 'Invalid Token'], 400);
        }

        // check if the token is expired
        if (Carbon::parse($tokenData->created_at)->addMinutes(10)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['error' => 'Token has expired'], 400);
        }

        //dont allow reuse of old password
        $user = User::where('email', $request->email)->first();

        if (strlen($user->password) === 40 || $user->password_algorithm === 'sha1' || $user->password_algorithm === null) {
            if (sha1($request->password) === $user->password) {
                return response()->json(['message' => "New password cannot be the same as the old password"], 400);
            }
        } else {
            if (password_verify($request->password, $user->password)) {
                return response()->json(['message' => "New password cannot be the same as the old password"], 400);
            }
        }

        // update the user password
        $user->update(['password' => bcrypt($request->password), 'password_algorithm' => 'bcrypt']);

        // delete the token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // return a response
        return response()->json(['message' => 'Password reset successfully'], 200);
    }
}
