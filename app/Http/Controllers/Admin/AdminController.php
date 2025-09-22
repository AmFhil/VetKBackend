<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Mail\message;
use App\Events\vet_notification;
use App\Models\AdminActivity;
use App\Models\Clinic;
use App\Models\Farm;
use App\Models\ForumChat;
use App\Models\livestock_farmer;
use App\Models\Others;
use App\Models\Pet;
use App\Models\Pet_Owner;
use App\Models\Products;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VeterinaryClinic;
use App\Models\VeterinaryDoctor;
use App\Models\VeterinaryParaprofessional;
use App\Notifications\MessageNotification;
use App\Traits\BroadcastsAdminEvents;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use stdClass;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    use BroadcastsAdminEvents;
    //

    //Admin edit profile
    // public function updateAdminProfile(Request $request)
    // {
    //     $count = strlen($request->profile_picture);
    //     $sliceString = strpos($request->profile_picture, 'pic/');
    //     $saveImage = '';
    //     if (Str::contains($request->profile_picture, 'wamp') || Str::contains($request->profile_picture, 'tmp')) {
    //         $imageName = mt_rand(1000, 9000);
    //         $extension = $request->profile_picture->extension();
    //         $image = $imageName . '.' . $extension;
    //         $myimage = 'public/admin_pic';
    //         $saveImage = $image;
    //         $path = $request->profile_picture->storeAs($myimage, $image);
    //     } else {
    //         $saveImage  = substr($request->profile_picture, $sliceString + 4, $count);
    //     }
    //     $success = DB::table('admin')->where([['staff_id', $request->staff_id]])->update([
    //         'first_name' => $request->first_name,
    //         'last_name' => $request->last_name,
    //         'phone_number' => $request->phone_number,
    //         'location' => $request->location,
    //         'profile_picture' => $saveImage
    //     ]);
    //     if ($request->has('password')) {
    //         // $finalSuccess = DB::table('admin')->where([['staff_id', $request->staff_id], ['password', sha1($request->password)]])->update([
    //         //     'password' => sha1($request->new_password),
    //         // ]);
    //         $finalSuccess = DB::table('admin')->where('staff_id', $request->staff_id)->first();

    //         // check bcrypt
    //         if (Hash::check($request->password, $finalSuccess->password)) {
    //             $finalSuccess->update([
    //                 'password' => Hash::make($request->password)
    //             ]);
    //         } elseif ($finalSuccess->password === sha1($request->password)) {
    //             $finalSuccess->update([
    //                 'password' => Hash::make($request->password)
    //             ]);
    //         }

    //         if ($finalSuccess) {
    //             $admin = DB::table('admin')->where([['staff_id', $request->staff_id]])->first();
    //             if ($admin) {
    //                 $adminUser = DB::table('admin')->where('email', $admin->email)->first();
    //                 if ($adminUser) {
    //                     $adminUser->notify(new \App\Notifications\AdminActionNotification(
    //                         'Profile Update',
    //                         'Hi ' . $request->first_name . ',<br>This is to notify you that your profile was updated successfully.'
    //                     ));
    //                 }
    //             }
    //             $request->merge(['staff_id' => $request->staff_id, 'title' => 'Profile Update', 'detail' => 'Hi ' . $request->first_name . ', ' . '<br>' . 'This is to notify you that your profile was updated successfully.']);
    //             $this->addAdminActivity($request);
    //             return (['detail' => 'Your Account has been updated successfully']);
    //         } else {
    //             return (['code' => 400, 'detail' => 'Incorrect Password/No data changed']);
    //         }
    //     } else {
    //         if ($success) {
    //             $request->merge(['staff_id' => $request->staff_id, 'title' => 'Profile Update', 'detail' => 'Hi ' . $request->first_name . ', ' . '<br>' . 'This is to notify you that your profile was updated successfully.']);
    //             $this->addAdminActivity($request);
    //             $admin = DB::table('admin')->where([['staff_id', $request->staff_id]])->get()->first();
    //             if ($admin) {
    //                 $adminUser = DB::table('admin')->where('email', $admin->email)->first();
    //                 if ($adminUser) {
    //                     $adminUser->notify(new \App\Notifications\AdminActionNotification(
    //                         'Profile Update',
    //                         'Hi ' . $request->first_name . ',<br>This is to notify you that your profile was updated successfully.'
    //                     ));
    //                 }
    //             }
    //             $admin->role = 'Admin';
    //             $admin->profile_picture = ('https://vetkonect.com/backend/storage/app/public/admin_pic/' . $admin->profile_picture);
    //             return (['detail' => 'Your Account has been updated successfully', 'data' => $admin]);
    //         } else {
    //             return (['code' => 400, 'detail' => 'Something went wrong/No data changed']);
    //         }
    //     }
    // }

    //Get Admin Activities
    public function getAdminActivity(Request $request)
    {
        $adminActivity = AdminActivity::where([['admin_id', $request->admin_id]])->orderBy('id', 'DESC')->get();
        return $adminActivity;
    }


    //get admin activities by filter
    public function getAdminActivityByFilter(Request $request)
    {
        if ($request->has('name')) {
            $activity = AdminActivity::where('admin_id', $request->admin_id)
                ->where('title', 'like', '%' . $request->name . '%')
                ->get();
        }
        return $activity;
    }

    public function searchAdminActivity(Request $request)
    {
        $search = $request->input('query');
        $activities = AdminActivity::where('admin_id', $request->admin_id)
            ->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('detail', 'like', '%' . $search . '%')
                    ->orWhere('action', 'like', '%' . $search . '%')
                    ->orWhere('meta', 'like', '%' . $search . '%');
            })
            ->get();

        return response()->json(['activities' => $activities]);
    }

    //Admin adds or edits promotion plan
    // public function addPromotionPlan(Request $request)
    // {
    //     $id = 'pp' . '' . mt_rand(1000, 9000);
    //     $permission = DB::table('admin')->where([['staff_id', $request->staff_id], ['permission_level', 1]])->get()->first();
    //     if ($permission) {
    //         $check = DB::table('promotion_plan')->where('promotion_id', $request->promotion_id)->get()->first();
    //         $title = DB::table('promotion_plan')->where('promotion_title', $request->title)->get()->first();
    //         if (!$check) {
    //             if (!$title) {
    //                 $success = DB::table('promotion_plan')->insert([
    //                     'promotion_title' => $request->title,
    //                     'promotion_id' => $id,
    //                     'currency' => $request->currency,
    //                     'price' => $request->price,
    //                     'vat' => $request->vat,
    //                     'date_option' => $request->date_option,
    //                     'duration' => $request->duration,
    //                     'no_of_products' => $request->no_of_product,
    //                     'date' => Carbon::now()->toDateTimeString(),
    //                 ]);
    //                 if ($success) {
    //                     $request->merge(['staff_id' => $request->id, 'title' => 'Added Promotion Plan', 'detail' => 'Hi ' . $request->first_name . ', ' . '<br>' . 'You added new Promotion Plan']);
    //                     $this->addAdminActivity($request);
    //                     return (['detail' => 'Promotion plan added successfully']);
    //                 } else {
    //                     return (['detail' => 'Something went wrong! Try again']);
    //                 }
    //             } else {
    //                 return (['code' => 400, 'detail' => 'This Title already exists!']);
    //             }
    //         } else {
    //             $success = DB::table('promotion_plan')->where('promotion_id', $request->promotion_id)->update([
    //                 'promotion_title' => $request->title,
    //                 'price' => $request->price,
    //                 'vat' => $request->vat,
    //                 'date_option' => $request->date_option,
    //                 'duration' => $request->duration,
    //                 'no_of_products' => $request->no_of_product,
    //                 'date' => now(),
    //             ]);
    //             if ($success) {
    //                 $request->merge(['staff_id' => $request->id, 'title' => 'Updated Promotion Plan', 'detail' => 'Hi ' . $request->first_name . ', ' . '<br>' . 'You updated a Promotion Plan']);
    //                 $this->addAdminActivity($request);
    //                 return (['detail' => 'Promotion plan updated successfully']);
    //             } else {
    //                 return (['code' => 400, 'detail' => 'Something went wrong! Try again']);
    //             }
    //         }
    //     } else {
    //         return (['code' => 400, 'detail' => 'You have no permission to do this']);
    //     }
    // }


    // public function deleteUserPromotionPlan(Request $request)
    // {
    //     $check = DB::table('user_promotion_plan')->where('promotion_id', $request->promotion_id)->get()->first();
    //     if ($check) {
    //         return (['code' => 400, 'detail' => 'Users are attached to this plan, you can not delete the plan']);
    //     } else {
    //         try {
    //             $success = DB::table('promotion_plan')->where('promotion_id', $request->promotion_id)->delete();
    //             if ($success) {
    //                 return (['detail' => 'Promotion plan deleted successfully']);
    //             }
    //         } catch (\Throwable $th) {
    //             return (['code' => 400, 'detail' => $th->errorInfo[2]]);
    //         }
    //     }
    // }

    //Admin adds or edits subscription plan
    // public function addSubscriptionPlan(Request $request)
    // {
    //     $id = 'sp' . '' . mt_rand(1000, 9000);
    //     $permission = DB::table('admin')->where([['staff_id', $request->staff_id], ['permission_level', 1]])->get()->first();
    //     if ($permission) {
    //         $check = DB::table('subscription_plan')->where('subscription_id', $request->plan_id)->get()->first();
    //         $title = DB::table('subscription_plan')->where('subscription_title', $request->title)->get()->first();
    //         if (!$check) {
    //             if (!$title) {
    //                 try {
    //                     $success = DB::table('subscription_plan')->insert([
    //                         'subscription_title' => $request->title,
    //                         'subscription_id' => $id,
    //                         'currency' => $request->currency,
    //                         'price' => $request->price,
    //                         'vat' => $request->vat,
    //                         'date_option' => $request->date_option,
    //                         'duration' => $request->duration,
    //                         'case' => $request->case,
    //                         'contact_info' => $request->contact_info,
    //                         'direct_message' => $request->direct_message,
    //                         'feed_calculator' => $request->feed_calculator,
    //                         'disease_predictor' => $request->disease_predictor,
    //                         'store' => $request->store,
    //                         'no_of_products' => $request->no_of_product,
    //                         'customer_support' => $request->customer_support,
    //                         'date' => now(),
    //                     ]);
    //                     if ($success) {
    //                         $request->merge(['staff_id' => $request->id, 'title' => 'Added Subscription Plan', 'detail' => 'Hi Admin' . ', ' . '<br>' . 'You added new Subscription Plan']);
    //                         $this->addAdminActivity($request);
    //                         return (['detail' => 'Subscription plan added successfully']);
    //                     } else {
    //                         return (['code' => 400, 'detail' => 'Something went wrong! Try again']);
    //                     }
    //                 } catch (\Throwable $th) {
    //                     return (['code' => 400, 'detail' => $th->errorInfo[2]]);
    //                 }
    //             } else {
    //                 return (['code' => 400, 'detail' => 'This Title already exists!']);
    //             }
    //         } else {
    //             try {
    //                 $success = DB::table('subscription_plan')->where('subscription_id', $request->plan_id)->update([
    //                     'price' => $request->price,
    //                     'vat' => $request->vat,
    //                     'date_option' => $request->date_option,
    //                     'duration' => $request->duration,
    //                     'case' => $request->case,
    //                     'contact_info' => $request->contact_info,
    //                     'direct_message' => $request->direct_message,
    //                     'feed_calculator' => $request->feed_calculator,
    //                     'disease_predictor' => $request->disease_predictor,
    //                     'store' => $request->store,
    //                     'no_of_products' => $request->no_of_product,
    //                     'customer_support' => $request->customer_support,
    //                     'date' => now(),
    //                 ]);
    //                 if ($success) {
    //                     $request->merge(['staff_id' => $request->id, 'title' => 'Updated Promotion Plan', 'detail' => 'Hi Admin' . ', ' . '<br>' . 'You updated a Subscription Plan']);
    //                     $this->addAdminActivity($request);
    //                     return (['detail' => 'Subscription plan updated successfully']);
    //                 } else {
    //                     return (['code' => 400, 'detail' => 'Something went wrong! Try again']);
    //                 }
    //             } catch (\Throwable $th) {
    //                 return (['code' => 400, 'detail' => $th->errorInfo[2]]);
    //             }
    //         }
    //     } else {
    //         return (['code' => 400, 'detail' => 'You have no permission to do this!']);
    //     }
    // }

    // public function deleteSubscriptionPlan(Request $request)
    // {
    //     $check = DB::table('vet_plan')->where('subscription_id', $request->plan_id)->get()->first();
    //     if ($check) {
    //         return (['code' => 400, 'detail' => 'Users are attached to this plan, you can not delete the plan']);
    //     } else {
    //         DB::table('subscription_plan')->where('plan_id', $request->plan_id)->delete();
    //         return (['detail' => 'Subscription plan deleted successfully']);
    //     }
    // }

    //approve forum chat content
    public function approveForumChat(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $content = ForumChat::where([['id', $request->forum_chat_id], ['user_id', $request->user_id]])->get()->first();
        try {
            $success = ForumChat::where([['id', $request->forum_chat_id], ['user_id', $request->user_id]])->update([
                'status' => 'published'
            ]);
            if ($success) {
                $this->createAdminActivity(
                    $admin->id,
                    $content->title . ' has been reviewed and approved by you',
                    "Forum Chat Content Approved",
                    "Hi user, your content with title: - ' . $content->title . ' has been reviewed and it has been approved to be on our platform'.",
                    ['forum_id' => $content->id, 'staff_id' => $admin->staff_id, 'role' => $request->user_role]
                );
                // Send notification to user
                $user = \App\Models\User::where('id', $request->user_id)->first();
                if ($user) {
                    $user->notify(new \App\Notifications\AdminActionNotification(
                        'Forum Chat Content Approved',
                        'Hi user, your content with title: - ' . $content->title . ' has been reviewed and it has been approved to be on our platform'
                    ));
                }
                $this->createAdminNotification(
                    $admin->id,
                    "Forum Chat Content Approved",
                    'forum',
                    "Hi user, your content with title: - ' . $content->title . ' has been reviewed and it has been approved to be on our platform'.",
                    ['forum_id' => $content->id, 'staff_id' => $admin->staff_id, 'role' => $request->user_role]
                );
                return ['detail' => 'You successfully approved a Forum Chat Content'];
            } else {
                return (['code' => 400, 'detail' => 'Something went wrong! Try again']);
            }
        } catch (\Throwable $th) {
            return (['code' => 400, 'detail' => $th->errorInfo[2]]);
        }
    }

    //reject forum chat content
    public function rejectForumChat(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $content = DB::table('forum_chat')->where([['id', $request->forum_chat_id], ['user_id', $request->user_id]])->get()->first();
        try {
            $success = DB::table('forum_chat')->where([['id', $request->forum_chat_id], ['user_id', $request->user_id]])->update([
                'status' => 'archived'
            ]);
            if ($success) {
                $this->createAdminActivity(
                    $admin->id,
                    $content->title . ' has been reviewed and rejected by you',
                    "Forum Chat Content Rejected",
                    "Hi user, we are sorry to let you know that your content with title: - ' . $content->title . ' has been reviewed and it has been rejected'.",
                    ['forum_id' => $content->id, 'staff_id' => $admin->staff_id, 'role' => $request->user_role]
                );
                // Send notification to user
                $user = \App\Models\User::where('id', $request->user_id)->first();
                if ($user) {
                    $user->notify(new \App\Notifications\AdminActionNotification(
                        'Forum Chat Content Rejected',
                        'Hi user, we are sorry to let you know that your content with title: - ' . $content->title . ' has been reviewed and it has been rejected'
                    ));
                }
                $this->createAdminNotification(
                    $admin->id,
                    "Forum Chat Content Rejected",
                    'forum',
                    "Hi user, your content with title: - ' . $content->title . ' has been reviewed and it has been rejected to be on our platform'.",
                    ['forum_id' => $content->id, 'staff_id' => $admin->staff_id, 'role' => $request->user_role]
                );

                return ['detail' => 'You successfully rejected a Forum Chat Content'];
            } else {
                return (['code' => 400, 'detail' => 'Something went wrong! Try again']);
            }
        } catch (\Throwable $th) {
            return (['code' => 400, 'detail' => $th->errorInfo[2]]);
        }
    }
    //verify vet number
    // public function verifyVetNumber(Request $request)
    // {
    //     try {
    //         $user = DB::table('veterinarian')->where('id', $request->id)->get()->first();
    //         $success = DB::table('veterinarian')->where('id', $request->id)->update([
    //             'vet_number_status' => 'Verified'
    //         ]);
    //         if ($success) {
    //             DB::table('vet_verification_pin')->insert([
    //                 'vet_email' => $user->email,
    //                 'vet_pin' => $user->vet_number,
    //                 'pin_status' => 1,
    //             ]);
    //             $request->merge(['user_id' => $request->id, 'role' => 'Veterinarian', 'title' => 'Vet Number verified', 'content' => 'Congratulations user, your Vet Number has been verified']);
    //             // Send notification to user
    //             $userModel = \App\Models\User::where('email', $user->email)->first();
    //             if ($userModel) {
    //                 $userModel->notify(new \App\Notifications\AdminActionNotification(
    //                     'Vet Number Verified',
    //                     'Congratulations ' . $user->first_name . ', We are so pleased to inform you that your Vet Number has been verified and your account is now visible on our platform.'
    //                 ));
    //             }
    //             $this->addAdminActivity($request);
    //             return ['detail' => 'You successfully verified Vet Number'];
    //         } else {
    //             return (['code' => 400, 'detail' => 'Something went wrong! Try again']);
    //         }
    //     } catch (\Throwable $th) {
    //         return (['code' => 400, 'detail' => $th->errorInfo[2]]);
    //     }
    // }

    //verify license number
    // public function verifyLicenseNumber(Request $request)
    // {
    //     try {
    //         $success = DB::table('clinic')->where([['id', $request->id], ['user_id', $request->user_id]])->update([
    //             'license_status' => 'Verified'
    //         ]);
    //         if ($success) {
    //             $request->merge(['user_id' => $request->user_id, 'role' => 'Veterinarian', 'title' => 'License Number verified', 'content' => 'Congratulations user, your Clinic License Number has been verified']);
    //             // Send notification to user
    //             $userModel = \App\Models\User::where('id', $request->user_id)->first();
    //             if ($userModel) {
    //                 $userModel->notify(new \App\Notifications\AdminActionNotification(
    //                     'License Number Verified',
    //                     'Congratulations, your Clinic License Number has been verified.'
    //                 ));
    //             }
    //             return ['detail' => 'You successfully verified Clinic License Number'];
    //         } else {
    //             return (['code' => 400, 'detail' => 'Something went wrong! Try again']);
    //         }
    //     } catch (\Throwable $th) {
    //         return (['code' => 400, 'detail' => $th->errorInfo[2]]);
    //     }
    // }

    //activate a user account
    // public function activateAccount(Request $request)
    // {
    //     $db = '';
    //     $attached = '';
    //     if ($request->role == 'Veterinarian') {
    //         $db = 'veterinarian';
    //     } else {
    //         $db = 'animal_owner';
    //     }
    //     try {
    //         $success = DB::table($db)->where([['id', $request->id]])->update([
    //             'account_activation' => 'Activated'
    //         ]);
    //         if ($success) {
    //             $user = DB::table($db)->get()->first();
    //             $userModel = User::where('email', $user->email)->first();
    //             $message = 'Dear ' . $user->first_name . ',' . '<br>' . 'We are so pleased to inform you that your account has now been activated ' . $attached . '<br><br>' . 'Yours truly,' . '<br><br>' . 'vetkonect Team.';
    //             if ($userModel) {
    //                 $userMail = DB::table('admin')->where('email', $user->email)->first();
    //                 if ($userMail) {
    //                     $userMail->notify(new \App\Notifications\MessageNotification(
    //                         'NO RESPONSE REPLY',
    //                         $message
    //                     ));
    //                 }
    //             }
    //             // $data = ['name' => $user->first_name, 'subject' => 'NO RESPONSE REPLY', 'view' => 'message', 'message' => $message];
    //             // $r = new message($data);
    //             // Mail::to($user->email)->send($r);
    //             return ['detail' => 'You successfully activated an account'];
    //         } else {
    //             return (['code' => 400, 'detail' => 'Something went wrong! Try again']);
    //         }
    //     } catch (\Throwable $th) {
    //         return (['code' => 400, 'detail' => $th->errorInfo[2]]);
    //     }
    // }

    //deactivate a user account
    // public function deactivateAccount(Request $request)
    // {
    //     $db = '';
    //     $attached = '';
    //     if ($request->role == 'Veterinarian') {
    //         $db = 'veterinarian';
    //     } else {
    //         $db = 'animal_owner';
    //     }
    //     try {
    //         $success = DB::table($db)->where([['id', $request->id]])->update([
    //             'account_activation' => 'Deactivated'
    //         ]);
    //         if ($success) {
    //             $user = DB::table($db)->get()->first();
    //             $message = 'Dear ' . $user->first_name . ',' . '<br>' . 'We are so displeased to inform you that your account has now been deactivated ' . $attached . '<br><br>' . 'Yours truly,' . '<br><br>' . 'vetkonect Team.';
    //             $userModel = User::where('email', $user->email)->first();
    //             if ($userModel) {
    //                 $userMail = DB::table('admin')->where('email', $user->email)->first();
    //                 if ($userMail) {
    //                     $userMail->notify(new MessageNotification(
    //                         'No Response Reply',
    //                         $message
    //                     ));
    //                 }
    //             }
    //         }
    //         // $data = ['name' => $user->first_name, 'subject' => 'NO RESPONSE REPLY', 'view' => 'message', 'message' => $message];
    //         // $r = new message($data);
    //         // Mail::to($user->email)->send($r);
    //         return ['detail' => 'You successfully deactivated an account'];
    //     } catch (\Throwable $th) {
    //         return (['code' => 400, 'detail' => $th->errorInfo[2]]);
    //     }
    // }

    //suspend a user promotion
    public function activatePromotion(Request $request)
    {
        $request->validate([
            'user_id' => 'required|in:users_registration',
            'role' => 'required|string'
        ]);
        try {
            $success = DB::table('ads_promotion')->where([['user_id', $request->user_id], ['role', $request->user_role]])->update([
                'suspended' => 0
            ]);
            if ($success) {
                return ['detail' => 'You successfully activated a promotion'];

                $user->sendNotification(
                    'ad_promotion activated',
                    'Ad Promoted Activated',
                    "Your advertisement <strong>{$ad->product_title}</strong> has been activated and is now visible to buyers. Good Luck!",
                    ['ad_id' => $ad->id]
                );
            } else {
                return (['code' => 400, 'detail' => 'Something went wrong! Try again']);
            }
        } catch (\Throwable $th) {
            return (['code' => 400, 'detail' => $th->errorInfo[2]]);
        }
    }
    //activate a user promotion
    public function deactivatePromotion(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|in:users_registration',
                'role' => 'required|string'
            ]);
            $success = DB::table('ads_promotion')->where([['user_id', $request->user_id], ['role', $request->user_role]])->update([
                'suspended' => 1
            ]);
            if ($success) {
                return ['detail' => 'You successfully deactivated a promotion'];

                $user = User::where('id', $request->user_id)->first();

                $user->sendNotification(
                    'ad_promotion de-activated',
                    'Ad Promoted De-activated',
                    "Your advertisement <strong>{$ad->product_title}</strong> has been de-activated and is not visible to buyers. Contact admin if mistaken!",
                    ['ad_id' => $ad->id]
                );
            } else {
                return (['code' => 400, 'detail' => 'Something went wrong! Try again']);
            }
        } catch (\Throwable $th) {
            return (['code' => 400, 'detail' => $th->errorInfo[2]]);
        }
    }

    //get user counters
    // public function usersCounter(Request $request)
    // {
    //     $user = new stdClass;
    //     if ($request->has('name')) {
    //         $vet = DB::table('veterinarian')->where([['category', null], ['country', $request->name]])->get();
    //         $paravet = DB::table('veterinarian')->where([['category', 'Paravet'], ['country', $request->name]])->get();
    //         $animal_owner = DB::table('animal_owner')->where('country', $request->name)->get();
    //         $active_vet = DB::table('veterinarian')->where([['account_activation', 'Activated'], ['vet_number_status', 'Verified'], ['category', null]])->where('country', $request->name)->get();
    //         $active_paravet = DB::table('veterinarian')->where([['account_activation', 'Activated'], ['vet_number_status', 'Verified'], ['category', 'Paravet']])->where('country', $request->name)->get();
    //         $inactive_vet = DB::table('veterinarian')->where([['account_activation', 'Not Activated'], ['category', null]])->where('country', $request->name)->get();
    //         $inactive_paravet = DB::table('veterinarian')->where([['account_activation', 'Not Activated'], ['category', 'Paravet']])->where('country', $request->name)->get();
    //         $inactive_animal_owner = DB::table('animal_owner')->where([['account_activation', 'Not Activated']])->where('country', $request->name)->get();
    //         $active_animal_owner = DB::table('animal_owner')->where('account_activation', 'Activated')->where('country', $request->name)->get();
    //         $verified_vet = DB::table('veterinarian')->where([['vet_number_status', 'Verified'], ['category', null]])->where('country', $request->name)->get();
    //         $unverified_vet = DB::table('veterinarian')->where([['vet_number_status', 'Not Verified'], ['category', null]])->where('country', $request->name)->get();
    //         $verified_paravet = DB::table('veterinarian')->where([['vet_number_status', 'Verified'], ['category', 'Paravet']])->where('country', $request->name)->get();
    //         $unverified_paravet = DB::table('veterinarian')->where([['vet_number_status', 'Not Verified'], ['category', 'Paravet']])->where('country', $request->name)->get();
    //         $user->total_user = count($vet) + count($animal_owner);
    //         $user->veterinarian = count($vet);
    //         $user->veterinarian = count($paravet);
    //         $user->active_vet = count($active_vet);
    //         $user->active_paravet = count($active_paravet);
    //         $user->animal_owner = count($animal_owner);
    //         $clinic = DB::table('clinic')->join('veterinarian', 'clinic.user_id', '=', 'veterinarian.id')->where('veterinarian.country', $request->name)->get();
    //         $livestock_farmer = [];
    //         $pet_owner = [];
    //         $pet = DB::table('pet')->join('animal_owner', 'pet.user_id', '=', 'animal_owner.id')->where('animal_owner.country', $request->name)->get();
    //         $farm = DB::table('farm')->join('animal_owner', 'farm.user_id', '=', 'animal_owner.id')->where('animal_owner.country', $request->name)->get();
    //         $livestock_farmer = DB::table('animal_owner')->where('category', 'Livestock Farmer')->get();
    //         $pet_owner = DB::table('animal_owner')->where('category', 'Pet Owner')->get();
    //         $vendor = DB::table('animal_owner')->where('category', 'Vendor')->get();
    //         $others = DB::table('animal_owner')->where('category', 'Others')->get();
    //         $pett = ($pet_owner);
    //         $pets = ($pett);
    //         $farmm = ($livestock_farmer);
    //         $farms = ($farmm);
    //         $store = DB::table('store')->join('veterinarian', 'store.user_id', '=', 'veterinarian.id')->where('veterinarian.country', $request->name)->get();
    //         $store2 = DB::table('store')->join('animal_owner', 'store.user_id', '=', 'animal_owner.id')->where('animal_owner.country', $request->name)->get();
    //         $user->pet = count($pets);
    //         $user->farm = count($farms);
    //         $user->clinic = count($clinic);
    //         $user->store = count($store) + count($store2);
    //         $user->verified_vet = count($verified_vet);
    //         $user->verified_paravet = count($verified_paravet);
    //         $user->pending_vet = count($inactive_vet);
    //         $user->pending_paravet = count($inactive_paravet);
    //         $user->pending_animal_owner = count($inactive_animal_owner);
    //         $user->unverified_vet = count($unverified_vet);
    //         $user->unverified_paravet = count($unverified_paravet);
    //         $user->vendor = count($vendor);
    //         $user->others = count($others);
    //         $user->active_user = count($active_vet) + count($active_animal_owner) + count($active_paravet);
    //     } else {
    //         $vet = DB::table('veterinarian')->where([['category', null]])->get();
    //         $paravet = DB::table('veterinarian')->where([['category', 'Paravet']])->get();
    //         $animal_owner = DB::table('animal_owner')->get();
    //         $livestock_farmer = [];
    //         $pet_owner = [];
    //         $pet = DB::table('pet')->get();
    //         $farm = DB::table('farm')->get();
    //         $livestock_farmer = DB::table('animal_owner')->where('category', 'Livestock Farmer')->get();
    //         $pet_owner = DB::table('animal_owner')->where('category', 'Pet Owner')->get();
    //         $vendor = DB::table('animal_owner')->where('category', 'Vendor')->get();
    //         $others = DB::table('animal_owner')->where('category', 'Others')->get();
    //         $pett = ($pet_owner);
    //         $pets = ($pett);
    //         $farmm = ($livestock_farmer);
    //         $farms = ($farmm);
    //         $clinic = DB::table('clinic')->get();
    //         $store = DB::table('store')->get();
    //         $active_vet = DB::table('veterinarian')->where([['account_activation', 'Activated'], ['vet_number_status', 'Verified'], ['category', null]])->get();
    //         $active_paravet = DB::table('veterinarian')->where([['account_activation', 'Activated'], ['vet_number_status', 'Verified'], ['category', 'Paravet']])->get();
    //         $inactive_vet = DB::table('veterinarian')->where([['account_activation', 'Not Activated'], ['category', null]])->get();
    //         $inactive_paravet = DB::table('veterinarian')->where([['account_activation', 'Not Activated'], ['category', 'Paravet']])->get();
    //         $inactive_animal_owner = DB::table('animal_owner')->where([['account_activation', 'Not Activated']])->get();
    //         $active_animal_owner = DB::table('animal_owner')->where('account_activation', 'Activated')->get();
    //         $verified_vet = DB::table('veterinarian')->where([['vet_number_status', 'Verified'], ['category', null]])->get();
    //         $unverified_vet = DB::table('veterinarian')->where([['vet_number_status', 'Not Verified'], ['category', null]])->get();
    //         $verified_paravet = DB::table('veterinarian')->where([['vet_number_status', 'Verified'], ['category', 'Paravet']])->get();
    //         $unverified_paravet = DB::table('veterinarian')->where([['vet_number_status', 'Not Verified'], ['category', 'Paravet']])->get();
    //         $user->total_user = count($vet) + count($animal_owner);
    //         $user->veterinarian = count($vet);
    //         $user->paravet = count($paravet);
    //         $user->animal_owner = count($animal_owner);
    //         $user->pet = count($pets);
    //         $user->farm = count($farms);
    //         $user->vendor = count($vendor);
    //         $user->others = count($others);
    //         $user->clinic = count($clinic);
    //         $user->store = count($store);
    //         $user->verified_vet = count($verified_vet);
    //         $user->verified_paravet = count($verified_paravet);
    //         $user->active_vet = count($active_vet);
    //         $user->active_paravet = count($active_paravet);
    //         $user->pending_vet = count($inactive_vet);
    //         $user->pending_paravet = count($inactive_paravet);
    //         $user->pending_animal_owner = count($inactive_animal_owner);
    //         $user->unverified_vet = count($unverified_vet);
    //         $user->unverified_paravet = count($unverified_paravet);
    //         $user->active_user = count($active_vet) + count($active_animal_owner) + count($active_paravet);
    //     }
    //     return $user;
    // }

    // count of all tables in the database
    public function adminGetUserCount()
    {
        $data = new stdClass;
        $data->veterinaryDoctor = VeterinaryDoctor::count();
        $data->veterinaryClinic = VeterinaryClinic::count();
        $data->veterinaryParaprofessional = VeterinaryParaprofessional::count();
        $data->pet_owner = Pet_Owner::count();
        $data->vendor = Vendor::count();
        $data->livestock_farmer = livestock_farmer::count();
        $data->other = Others::count();
        $data->users = User::count();
        $data->pets = Pet::count();
        $data->farms = Farm::count();
        $data->clinics = Clinic::count();
        $data->stores = Store::count();
        $data->products = Products::count();
        return $data;
    }


    // public function adminFilterUserCount(Request $request)
    // {
    //     if ($request->has('country')) {
    //         if ($request->category == 'paravet' || $request->category == '') {
    //             if ($request->category == 'paravet') {
    //                 if ($request->type == 'Activation') {
    //                     $vet = DB::table('veterinarian')->where([['category', 'Paravet'], ['country', $request->country], ['account_activation', 'Not Activated']])->orderBy('id', 'DESC')
    //                         ->get();
    //                 } else {
    //                     $vet = DB::table('veterinarian')->where([['category', 'Paravet'], ['country', $request->country], ['vet_number_status', 'Not Verified']])->orderBy('id', 'DESC')
    //                         ->get();
    //                 }
    //             } else {
    //                 if ($request->type == 'Activation') {
    //                     $vet = DB::table('veterinarian')->where([['category', null], ['country', $request->country], ['account_activation', 'Not Activated']])->orderBy('id', 'DESC')
    //                         ->get();
    //                 } else {
    //                     $vet = DB::table('veterinarian')->where([['category', null], ['country', $request->country], ['vet_number_status', 'Not Verified']])->orderBy('id', 'DESC')
    //                         ->get();
    //                 }
    //             }
    //             for ($i = 0; $i < count($vet); $i++) {
    //                 $vet[$i]->profile_picture = ('https://vetkonect.com/backend/storage/app/public/vet_user_pic/' . $vet[$i]->profile_picture);
    //             }
    //             return $vet;
    //         } else {
    //             $animal_owner = DB::table('animal_owner')->where([['account_activation', 'Not Activated'], ['country', $request->country]])->orderBy('id', 'DESC')
    //                 ->get();
    //             for ($i = 0; $i < count($animal_owner); $i++) {
    //                 $animal_owner[$i]->profile_picture = ('https://vetkonect.com/backend/storage/app/public/vet_user_pic/' . $animal_owner[$i]->profile_picture);
    //             }
    //             return $animal_owner;
    //         }
    //     } else {
    //         if ($request->category == 'paravet' || $request->category == '') {
    //             if ($request->category == 'paravet') {
    //                 if ($request->type == 'Activation') {
    //                     $vet = DB::table('veterinarian')->where([['category', 'Paravet'], ['account_activation', 'Not Activated']])->orderBy('id', 'DESC')
    //                         ->get();
    //                 } else {
    //                     $vet = DB::table('veterinarian')->where([['category', 'Paravet'], ['vet_number_status', 'Not Verified']])->orderBy('id', 'DESC')
    //                         ->get();
    //                 }
    //             } else {
    //                 if ($request->type == 'Activation') {
    //                     $vet = DB::table('veterinarian')->where([['category', null], ['account_activation', 'Not Activated']])->orderBy('id', 'DESC')
    //                         ->get();
    //                 } else {
    //                     $vet = DB::table('veterinarian')->where([['category', null], ['vet_number_status', 'Not Verified']])->orderBy('id', 'DESC')
    //                         ->get();
    //                 }
    //             }
    //             for ($i = 0; $i < count($vet); $i++) {
    //                 $vet[$i]->profile_picture = ('https://vetkonect.com/backend/storage/app/public/vet_user_pic/' . $vet[$i]->profile_picture);
    //             }
    //             return $vet;
    //         } else {
    //             $animal_owner = DB::table('animal_owner')->where('account_activation', 'Not Activated')->orderBy('id', 'DESC')
    //                 ->get();
    //             for ($i = 0; $i < count($animal_owner); $i++) {
    //                 $animal_owner[$i]->profile_picture = ('https://vetkonect.com/backend/storage/app/public/vet_user_pic/' . $animal_owner[$i]->profile_picture);
    //             }
    //             return $animal_owner;
    //         }
    //     }
    // }

    public function getAllVeterinaryParaprofessional()
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized']);
        $veterinaryParaprofessionals = VeterinaryParaprofessional::with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'name_of_institution', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability', 'created_at')
            ->latest()
            ->paginate(10);

        // add average rating to each veterinary paraprofessional
        $veterinaryParaprofessionals->getCollection()->transform(function ($veterinaryParaprofessional) {
            $veterinaryParaprofessional->average_rating = $veterinaryParaprofessional->averageRating();
            return $veterinaryParaprofessional;
        });

        return response()->json(['veterinary_paraprofessionals' => $veterinaryParaprofessionals], 200);
    }

    public function getAllVeterinaryDoctors()
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized']);

        $veterinaryDoctors = VeterinaryDoctor::with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'specialty', 'list_them', 'address', 'longitude', 'latitude', 'role', 'availability', 'created_at')
            ->paginate(10);

        // add average rating to each veterinary doctor
        $veterinaryDoctors->getCollection()->transform(function ($doctor) {
            $doctor->average_rating = $doctor->averageRating();
            return $doctor;
        });

        return response()->json(['veterinary_doctors' => $veterinaryDoctors], 200);
    }

     public function getAllVeterinaryClinics()
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized']);

        $clinics = VeterinaryClinic::with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'name_of_clinic', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability', 'created_at')
            ->inRandomOrder()
            ->paginate(10);

        // add average rating to each veterinary clinic
        $clinics->getCollection()->transform(function ($clinic) {
            $clinic->average_rating = $clinic->averageRating();
            return $clinic;
        });

        return response()->json(['clinics' => $clinics], 200);
    }

    public function searchVeterinaryParaprofessionals(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized']);

        $query = $request->input('query');

        if (!$query) {
            return response()->json(['veterinary_paraprofessionals' => []], 200);
        }

        $veterinaryParaprofessionals = VeterinaryParaprofessional::where('specialty', 'like', "%$query%")
            ->orWhere('name_of_institution', 'like', "%$query%")
            ->orWhere('list_them', 'like', "%$query%")
            ->orWhere('address', 'like', "%$query%")
            ->with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'name_of_institution', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability')
            ->paginate(10);

        // add average rating to each veterinary paraprofessional
        $veterinaryParaprofessionals->getCollection()->transform(function ($veterinaryParaprofessional) {
            $veterinaryParaprofessional->average_rating = $veterinaryParaprofessional->averageRating();
            return $veterinaryParaprofessional;
        });

        return response()->json(['veterinary_paraprofessionals' => $veterinaryParaprofessionals], 200);
    }

    public function searchVeterinaryDoctors(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized']);

        $query = $request->input('query');
        if (!$query) {
            return response()->json(['veterinary_doctors' => []], 200);
        }

        $veterinaryDoctors = VeterinaryDoctor::where('specialty', 'like', "%$query%")
            ->orWhere('list_them', 'like', "%$query%")
            ->orWhere('address', 'like', "%$query%")
            ->with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'specialty', 'list_them', 'address', 'longitude', 'latitude', 'role', 'availability')
            ->paginate(10);

        // add average rating to each veterinary doctor
        $veterinaryDoctors->getCollection()->transform(function ($doctor) {
            $doctor->average_rating = $doctor->averageRating();
            return $doctor;
        });

        return response()->json(['veterinary_doctors' => $veterinaryDoctors], 200);
    }

     public function searchVeterinaryClinics(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized']);

        $query = $request->input('query', '');

        if (!$query) {
            return response()->json(['clinics' => []], 200);
        }

        $clinics = VeterinaryClinic::where('name_of_clinic', 'like', "%$query%")
            ->orWhere('specialty', 'like', "%$query%")
            ->orWhere('list_them', 'like', "%$query%")
            ->orWhere('address', 'like', "%$query%")
            ->with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'name_of_clinic', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability')
            ->paginate(10);

        // add average rating to each veterinary clinic
        $clinics->getCollection()->transform(function ($clinic) {
            $clinic->average_rating = $clinic->averageRating();
            return $clinic;
        });

        return response()->json(['clinics' => $clinics], 200);
    }


    public function adminGetAnimalOwner(Request $request)
    {
        $animal_owner = DB::table('animal_owner')
            ->orWhere('first_name', 'like', '%' . $request->name . '%')
            ->orWhere('last_name', 'like', '%' . $request->name . '%')->orderBy('id', 'DESC')
            ->get();
        for ($i = 0; $i < count($animal_owner); $i++) {
            $animal_owner[$i]->profile_picture = ('https://vetkonect.com/backend/storage/app/public/vet_user_pic/' . $animal_owner[$i]->profile_picture);
        }
        return $animal_owner;
    }

    //get all clinics
    public function adminGetClinic(Request $request)
    {
        $clinic = DB::table('clinic')
            ->orWhere('clinic_name', 'like', '%' . $request->name . '%')
            ->orWhere('clinic_name', 'like', '%' . $request->name . '%')
            ->get();
        for ($i = 0; $i < count($clinic); $i++) {
            $clinic[$i]->picture = ('https://vetkonect.com/backend/storage/app/public/vet_clinic_pic/' . $clinic[$i]->picture);
        }
        return $clinic;
    }

    //get all farms
    public function adminGetFarm(Request $request)
    {
        $farm = DB::table('farm')
            ->orWhere('farm_name', 'like', '%' . $request->name . '%')
            ->get();
        for ($i = 0; $i < count($farm); $i++) {
            $farm[$i]->picture = ('https://vetkonect.com/backend/storage/app/public/vet_farm_pic/' . $farm[$i]->picture);
        }
        return $farm;
    }

    //get all pets
    public function adminGetPet(Request $request)
    {
        $pet = DB::table('pet')
            ->orWhere('pet_name', 'like', '%' . $request->name . '%')
            ->get();
        for ($i = 0; $i < count($pet); $i++) {
            $pet[$i]->picture = ('https://vetkonect.com/backend/storage/app/public/vet_pet_pic/' . $pet[$i]->picture);
        }
        return $pet;
    }


    //user can view all promotion plans
    public function adminGetPromotionPlan()
    {
        // $promotion_plan = [];
        // $set_plan = new stdClass;
        $plans = DB::table('promotion_plan')->get();
        return $plans;
    }

    //user can view all plans
    public function adminGetSubscriptionPlan()
    {
        // $subscription_plan = [];
        // $set_plan = new stdClass;
        $plans = DB::table('subscription_plan')->get();
        return $plans;
    }

    //get all products
    public function adminGetProduct(Request $request)
    {
        $product = DB::table('products')
            ->orWhere('title', 'like', '%' . $request->name . '%')
            ->get();
        if (count($product) > 0) {
            for ($j = 0; $j < count($product); $j++) {
                $promo_image = json_decode($product[$j]->images);
                $images = [];
                for ($k = 0; $k < count($promo_image); $k++) {
                    $images[] = ('https://vetkonect.com/backend/storage/app/public/vet_product_pic/' . $promo_image[$k]);
                }
                $role = '';
                if ($product[$j]->role == 'Veterinarian') {
                    $role = 'veterinarian';
                } else {
                    $role = 'animal_owner';
                }
                $user = DB::table($role)->where('id', $product[$j]->user_id)->get()->first();
                if ($user) {
                    $user->profile_picture = ('https://vetkonect.com/backend/storage/app/public/vet_user_pic/' . $user->profile_picture);
                    $product[$j]->first_name = $user->first_name;
                    $product[$j]->last_name = $user->last_name;
                    $product[$j]->category = $user->category ?? '';
                    $product[$j]->profile_picture = $user->profile_picture;
                }
                $product[$j]->images = $images;
                $images = [];
            }
        }
        return $product;
    }

    //get all promotion
    public function adminGetPromotion(Request $request)
    {
        $promotion = $this->getPromotion($request);
        return $promotion;
        // return $this->_group_by2($promotion);
    }

    //get all subscription
    public function adminGetSubscription(Request $request)
    {
        $vet = DB::table('veterinarian')
            ->orWhere('first_name', 'like', '%' . $request->name . '%')
            ->orWhere('last_name', 'like', '%' . $request->name . '%')
            ->get();
        $animal_owner = DB::table('animal_owner')
            ->orWhere('first_name', 'like', '%' . $request->name . '%')
            ->orWhere('last_name', 'like', '%' . $request->name . '%')
            ->get();
        $user = [];
        $allUser = [];
        foreach ($vet as $key) {
            $user[] = $key;
        }
        foreach ($animal_owner as $key) {
            $user[] = $key;
        }
        for ($i = 0; $i < count($user); $i++) {
            $subscription = DB::table('vet_plan')->where([['user_id', $user[$i]->id], ['role', $user[$i]->role]])->get()->first();
            if ($subscription) {
                $date_difference = ((Carbon::now()->diffInDays(Carbon::parse($subscription->date))));
                $id = $user[$i]->id;
                $role = $user[$i]->role;
                $user[$i]->profile_picture = ('https://vetkonect.com/backend/storage/app/public/vet_user_pic/'  . $user[$i]->profile_picture);
                $subscription = DB::table('vet_plan')->where([['user_id', $id], ['role', $role]])->get()->first();
                $date_difference = ((Carbon::now()->diffInDays(Carbon::parse($subscription->date))));
                $plans = DB::table('subscription_plan')->where('subscription_id', $subscription->subscription_id)->get()->first();
                $user[$i]->plan = $plans->subscription_title;
                $user[$i]->category = $user[$i]->category;
                $expiry_date = '';
                if ($plans->date_option == 'Days') {
                    $expiry_date = Carbon::parse($subscription->date)->addDays($plans->duration);
                } else if ($plans->date_option == 'Months') {
                    $expiry_date = Carbon::parse($subscription->date)->addMonths($plans->duration);
                }
                if ($date_difference > $plans->duration) {
                    $user[$i]->subscription = 'Expired';
                    $user[$i]->expiry_date = $expiry_date;
                } else {
                    $user[$i]->subscription = 'Active';
                    $user[$i]->expiry_date = $expiry_date;
                }
                $allUser[] = $user[$i];
            }
        }
        return $allUser;
        // return $this->_group_by($allUser, 'plan');
    }

    //reusable function that groups user in subscription plan type (Monthly, etc)
    public function _group_by($array, $key)
    {
        $user = array();
        foreach ($array as $val) {
            $user[$val->plan][] = $val;
        }
        return $user;
    }

    //reusable function that groups user in subscription plan type (Monthly, etc)
    public function _group_by2($array)
    {
        $promotion = array();
        foreach ($array as $val) {
            $promotion[$val->plan][] = $val;
        }
        return $promotion;
    }

    //reusable function to add admin activity
    public function addAdminActivity($request)
    {
        if ($request->staff_id == null) {
            $request->except('staff_id');
            $request->merge(['staff_id' => 'admin_001']);
        }
        DB::table('admin_activity')->insert([
            'admin_id' => $request->admin_id,
            'action' => $request->action,
            'title' => $request->title,
            'detail' => $request->detail,
            'meta' => $request->meta ?? [],
            'created_at' => Carbon::now()->toTimeString(),
        ]);
    }

    // public function adminAddNewVet(Request $request)
    // {
    //     //check whether email does not exist for any user;
    //     //   try {
    //     $data = $request->data;
    //     $emailMessage = '';
    //     $phoneMessage = '';
    //     $vcnMessage = '';
    //     $category = "Paravet";
    //     for ($i = 0; $i < count($data); $i++) {
    //         $email_exist = DB::table('veterinarian')
    //             ->where('email', $data[$i]['email'])
    //             ->get()->first();
    //         $email_exist1 = DB::table('animal_owner')
    //             ->where('email', $data[$i]['email'])
    //             ->get()->first();
    //         $phone_exist = DB::table('veterinarian')
    //             ->where('phone_number', $data[$i]['phone_number'])
    //             ->get()->first();
    //         $phone_exist1 = DB::table('animal_owner')
    //             ->where('phone_number', $data[$i]['phone_number'])
    //             ->get()->first();
    //         $speciality = [];
    //         $vet_number_exist = DB::table('veterinarian')->where('vet_number', $data[$i]['vet_number'])->get()->first();
    //         $vet_exist = false;
    //         if ($vet_number_exist && $data[$i]['vet_number'] != 'Awaiting') {
    //             $vet_exist = true;
    //         } else {
    //             if ($data[$i]['category'] == "Veterinarian") {
    //                 $category = null;
    //             } else {
    //                 $category = "Paravet";
    //             }
    //             $pincode = mt_rand(1000, 9000);
    //             $confirmVetNumber = DB::table('vet_verification_pin')->where('vet_pin', $data[$i]['vet_number'])->get()->first();
    //             $status = 'Not Verified';
    //             $attached = 'Your Vet Number is not found in our database, but we will make sure we verify it within seven working days and get in touch with you.';
    //             if ($confirmVetNumber) {
    //                 $status = 'Verified';
    //                 $attached = 'And we are glad that your Vet Number has been verified';
    //             }
    //             if (!$vet_exist && !$phone_exist && !$email_exist && !$phone_exist1 && !$email_exist1) {
    //                 DB::table('veterinarian')->insert([
    //                     'email' => $data[$i]['email'],
    //                     'password' => sha1($data[$i]['phone_number']),
    //                     'vet_number' => $data[$i]['vet_number'],
    //                     'category' => $category,
    //                     'first_name' => $data[$i]['first_name'],
    //                     'last_name' => $data[$i]['last_name'],
    //                     'phone_number' => $data[$i]['phone_number'],
    //                     'country' => $data[$i]['country'],
    //                     'account_activation' => 'Not Activated',
    //                     'vet_number_status' => $status,
    //                     'role' => 'Veterinarian',
    //                     'availability' => 1,
    //                     'date' => now()
    //                 ]);

    //                 $message = 'Dear ' . $data[$i]['first_name'] . ', Vet Konect is inviting you to activate your profile and join veterinary doctors across Africa to connect to millions of livestock farmers and pet owners across Africa who require your expertise. ' . $attached . '. Your default password is ' . $data[$i]['phone_number'] . '. <a href="https://vetkonect.com">Click here to begin</a><br>Yours truly,<br>vetkonect Team.';
    //                 $userModel = \App\Models\User::where('email', $data[$i]['email'])->first();
    //                 if ($userModel) {
    //                     $userModel->notify(new \App\Notifications\MessageNotification('Invite', $message));
    //                 }
    //             }
    //         }
    //         if ($i == (count($data) - 1)) {
    //             return ['detail' => 'Veterinarian details added successfully'];
    //         }
    //     }
    //     //           } catch (\Throwable $th) {
    //     //     return (['code' => 401, 'detail' => $th->errorInfo[2]]);
    //     // }
    // }
    //Admin adds category
    // public function adminAddCategory(Request $request)
    // {
    //     if (Str::contains($request->icon, 'wamp') || Str::contains($request->icon, 'tmp')) {
    //         $iconName = mt_rand(1000, 9000);
    //         $extension = $request->icon->extension();
    //         $icon = $iconName . '.' . $extension;
    //         $myIcon = 'public/icon';
    //         $saveIcon = $icon;
    //         $path = $request->icon->storeAs($myIcon, $icon);
    //     } else {
    //         $sliceString = strpos($request->icon, 'pic/');
    //         $count = strlen($request->icon);
    //         $icon  = substr($request->icon, $sliceString + 4, $count);
    //     }
    //     $id = 'sp' . '' . mt_rand(1000, 9000);
    //     // $permission = DB::table('admin')->where([['staff_id', $request->staff_id], ['permission_level', 1]])->get()->first();
    //     // if ($permission) {
    //     $name = DB::table('category')->where('id', $request->id)->get()->first();
    //     try {
    //         if (!$name) {
    //             DB::table('category')->insert([
    //                 'name' => $request->categoryName,
    //                 'icon' => $icon,
    //                 'date' => now(),
    //             ]);
    //             $request->merge(['staff_id' => $request->id, 'title' => 'Added Product Category', 'detail' => 'Hi Admin' . ', ' . '<br>' . 'You added new Product Category']);
    //             $this->addAdminActivity($request);
    //             return (['detail' => 'Category added successfully']);
    //         } else {
    //             DB::table('category')->insert([
    //                 'icon' => $icon,
    //             ]);
    //             $request->merge(['staff_id' => $request->id, 'title' => 'Edited Product Category', 'detail' => 'Hi Admin' . ', ' . '<br>' . 'You edited a Product Category']);
    //             $this->addAdminActivity($request);
    //             return (['detail' => 'Category edited successfully']);
    //         }
    //     } catch (\Throwable $th) {
    //         return (['code' => 400, 'detail' => $th->errorInfo[2]]);
    //     }
    // }

    //get all category
    // public function adminGetCategory()
    // {
    //     $category = DB::table('category')->get();
    //     if (count($category) > 0) {
    //         for ($j = 0; $j < count($category); $j++) {
    //             $category[$j]->icon = ('https://vetkonect.com/backend/storage/app/public/icon/' . $category[$j]->icon);
    //             $product = DB::table('products')->where('category', $category[$j]->name)->get();
    //             if ($product) {
    //                 $category[$j]->total = count($product);
    //             } else {
    //                 $category[$j]->total = 0;
    //             }
    //         }
    //     }
    //     return $category;
    // }
}
