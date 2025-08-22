<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    //
    public $passwordMatches = false;

     public function adminLogin(Request $request)
    {
        try {
            $request->validate([
                'staff_id' => 'required',
                'password' => 'required'
            ]);

            $admin = DB::table('admin')->where('staff_id', $request->staff_id)->first();

            if(!$admin){
                return response()->json(['error' => 'invalid credentials'], 401);
            }

            // check bcrypt
            if(Hash::check($request->password, $admin->password)){
                $this->passwordMatches = true;
            }

            elseif($admin->password === sha1($request->password)){
                $this->passwordMatches = true;

                // update password to bcrypt
                $admin->update([
                    'password' => Hash::make($request->password)
                ]);
            }

            if(!$this->passwordMatches){
                return response()->json(['error' => 'invalid credentials'], 401);
            }

            $admin->role = 'Admin';
            $admin->profile_picture = ('https://vetkonect.com/backend/storage/app/public/admin_pic/' . $admin->profile_picture);
            return response()->json(['message' => 'Login successful', 'admin' => $admin], 200);
        } catch (\Throwable $th) {
            return (['code' => 400, 'detail' => $th->errorInfo[2]]);
        }
    }
}
