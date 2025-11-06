<?php

namespace App\Http\Controllers;

use App\Models\Products;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Traits\BroadcastsAdminEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    use BroadcastsAdminEvents;
    //
    public function addNewStore(Request $request)
    {
        $request->merge([
            'availability' => filter_var($request->availability, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        ]);

        // validate the request
        $request->validate([
            'role' => 'required|string|in:vendor,pet_owner,others,livestock_owner,veterinary_doctor,veterinary_clinic,veterinary_paraprofessional',
            'store_name' => 'required|string|unique:store,store_name|max:100',
            'email' => 'required|email|unique:store,email|max:100',
            'phone_number' => 'required|string',
            'location' => 'required|string|max:255',
            'picture' => 'required|image|mimes:jpeg,png,jpg,svg|max:2048',
            'availability' => 'required|boolean',
            'longitude' => 'required|string|max:50',
            'latitude' => 'required|string|max:50',
        ],[
            'role.in' => 'The selected role is invalid. Valid roles are: vendor, pet owner, other, livestock owner, veterinary doctor, veterinary clinic, veterinary paraprofessional.'
        ]);

        // check if user is authenticated
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        // upload picture
        $imageName = $request->file('picture')->store('store_pictures', 'public');

        // create new store
        $store = Store::create([
            'user_id' => $user->id,
            'role' => optional($user->activeRole())->name,
            'store_name' => $request->store_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'location' => $request->location,
            'picture' => $imageName,
            'availability' => $request->availability,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'created_at' => Carbon::now()
        ]);

        $user->logActivity(
            'store_created',
            'Store Created',
            "Created store '{$store->store_name}'.",
            ['store_id' => $store->id]
        );

        $user->sendNotification(
            'store_created',
            'Store Created',
            "Your store '{$store->store_name}' has been successfully created.",
            ['store_id' => $store->id]
        );

        // return response
        return response()->json(['message' => 'Store created successfully.', 'store' => $store], 200);
    }

    public function updateStore(Request $request, $id)
    {
        $request->merge([
            'availability' => filter_var($request->availability, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        ]);

        $request->validate([
            'store_name'   => 'sometimes|string|unique:store,store_name,' . $id . '|max:100',
            'email'        => 'sometimes|email|unique:store,email,' . $id . '|max:100',
            'phone_number' => 'sometimes|string',
            'location'     => 'sometimes|string|max:255',
            'picture'      => 'sometimes|image|mimes:jpeg,png,jpg,svg|max:2048',
            'availability' => 'sometimes|boolean',
            'longitude'    => 'sometimes|string|max:50',
            'latitude'     => 'sometimes|string|max:50',
        ]);

        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized.'], 403);

        $store = Store::findOrFail($id);
        if ((int) $store->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden: not your store.'], 403);
        }

        // Replace picture if uploaded
        if ($request->hasFile('picture')) {
            if ($store->picture) {
                Storage::disk('public')->delete($store->picture);
            }
            $store->picture = $request->file('picture')->store('store_pictures', 'public');
        }

        $store->fill($request->only([
            'store_name',
            'email',
            'phone_number',
            'location',
            'availability',
            'longitude',
            'latitude'
        ]));
        $store->updated_at = Carbon::now();
        $store->save();

        // Log & notify
        $user->logActivity(
            'store_updated',
            'Store Updated',
            "Updated store '{$store->store_name}'.",
            ['store_id' => $store->id]
        );

        $user->sendNotification(
            'store_updated',
            'Store Updated',
            "Your store '{$store->store_name}' details have been updated.",
            ['store_id' => $store->id]
        );

        return response()->json(['message' => 'Store updated successfully.', 'store' => $store], 200);
    }

    // get vendor stores
    public function getVendorStores()
    {
        // check if user is authenticated
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        //get stores
        $stores = Store::with('ratings:id,rating,comment,rateable_id')
            ->where('role', 'Vendor')
            ->where('disabled', false)
            ->paginate(10);

        // get average rating for each store
        $stores->getCollection()->transform(function ($store) {
            $store->average_rating = $store->averageRating();
            // $store->average_rating = $average ? number_format($average, 1) . ' of 5' : '0 of 5';
            return $store;
        });

        return response()->json(['stores' => $stores], 200);
    }

    public function getAllStores()
    {
       // check if user is authenticated
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        //get stores
        $stores = Store::with('ratings:id,rating,comment,rateable_id')
            ->where('disabled', false)
            ->random()
            ->paginate(10);

        // get average rating for each store
        $stores->getCollection()->transform(function ($store) {
            $store->average_rating = $store->averageRating();
            return $store;
        });

        return response()->json(['stores' => $stores], 200);
    }

    public function getMyStore()
    {
          // check if user is authenticated
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        //get store
        $store = Store::with('ratings:id,rating,comment,rateable_id')
            ->where('user_id', $user->id)
            ->paginate(10);


        if (!$store) {
            return response()->json(['message' => 'Store not found.'], 404);
        }

        // get average rating
        $store->getCollection()->transform(function ($store) {
            $store->average_rating = $store->averageRating();
            // $store->average_rating = $average ? number_format($average, 1) . ' of 5' : '0 of 5';
            return $store;
        });

        return response()->json(['store' => $store], 200);
    }

    // get store by user id
    public function getStoreByUserId($user_id)
    {
        // check if user is authenticated
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        //get store
        $store = Store::with('ratings:id,rating,comment,rateable_id')
            ->where('user_id', $user_id)
            ->where('disabled', false)
            ->paginate(10);


        if (!$store) {
            return response()->json(['message' => 'Store not found.'], 404);
        }

        // get average rating
        $store->getCollection()->transform(function ($store) {
            $store->average_rating = $store->averageRating();
            // $store->average_rating = $average ? number_format($average, 1) . ' of 5' : '0 of 5';
            return $store;
        });

        return response()->json(['store' => $store], 200);
    }

    // get store by store id
    public function getStoreById($id)
    {
        // check if user is authenticated
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        //get store
        $store = Store::with('ratings:id,rating,comment,rateable_id')
            ->where('id', $id)
            ->first();

        if (!$store) {
            return response()->json(['message' => 'Store not found.'], 404);
        };

        // get average rating
        $store->average_rating = $store->averageRating();

        return response()->json(['store' => $store], 200);
    }

    public function getStoreByLocation(Request $request)
    {
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        if (!$latitude || !$longitude) {
            return response()->json(['message' => 'Latitude and Longitude are required.'], 400);
        }

        $store = Store::with([
            'user:id,email,first_name,last_name,phone_num,state,country',
            'user.profile:id,user_id,profile_image,bio,cover_page_image',
            'ratings:id,rateable_id,rating,comment'
        ])
            ->where('is_approved', 1)
            ->nearest($latitude, $longitude)
            ->where('disabled', false)
            ->paginate(10);

        // attach average rating
        $store->transform(function ($doctor) {
            $doctor->average_rating = $doctor->averageRating();
            return $doctor;
        });

        return response()->json(['message' => 'success', 'store' => $store], 200);
    }

    // delete store
    public function deleteStore($id)
    {
        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized.'], 403);

        $store = Store::findOrFail($id);
        if ((int) $store->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden: not your store.'], 403);
        }

        // Delete store picture
        if ($store->picture) {
            Storage::disk('public')->delete($store->picture);
        }

        // Delete products and their images
        $products = Products::where('store_id', $store->id)->get();
        foreach ($products as $product) {
            foreach (['image1', 'image2', 'image3', 'image4'] as $field) {
                if ($product->$field) {
                    Storage::disk('public')->delete($product->$field);
                }
            }
            $product->delete();
        }

        $storeName = $store->store_name;
        $storeId = $store->id;
        $store->delete();

        // Log & notify
        $user->logActivity(
            'store_deleted',
            'Store Deleted',
            "Deleted store '{$storeName}' and all related products.",
            ['store_id' => $storeId]
        );

        $user->sendNotification(
            'store_deleted',
            'Store Deleted',
            "Your store '{$storeName}' and its products have been removed.",
            ['store_id' => $storeId]
        );

        return response()->json(['message' => 'Store and related products deleted successfully.'], 200);
    }


    public function disableStore($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized.'], 403);

        $store = Store::findOrFail($id);
        $user  = User::find($store->user_id);

        if ($user) {
            $store->disabled = true;
            $store->save();

            // Notify user
            $user->sendNotification(
                'store_disabled',
                'Store Disabled',
                "Your store '{$store->store_name}' has been disabled by an administrator.",
                ['store_id' => $store->id, 'user_id' => $user->id]
            );

            $user->logActivity(
                'store_disabled',
                'Store Disabled',
                "Your store '{$store->store_name}' was disabled by an administrator.",
                ['store_id' => $store->id]
            );

            $user->notify(new \App\Notifications\AdminActionNotification(
                'Store Disabled by Administrator',
                'Dear ' . $user->first_name . ', your store "' . $store->store_name .
                    '" has been disabled by an administrator. If you believe this is an error, please contact support.'
            ));
        }

        // Log admin activity
        $this->createAdminActivity(
            $admin->id,
            'store_disabled',
            'STORE',
            "Admin {$admin->name} disabled {$user->first_name}'s store '{$store->store_name}' (ID: {$store->id}).",
            ['store_id' => $store->id, 'user_id' => $store->user_id, 'staff_id' => $admin->staff_id]
        );

        return response()->json(['message' => 'Store disabled successfully.'], 200);
    }
}
