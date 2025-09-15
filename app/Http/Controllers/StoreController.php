<?php

namespace App\Http\Controllers;

use App\Models\Products;
use App\Models\Store;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    //
    public function addNewStore(Request $request)
    {
        // validate the request
        $request->validate([
            'user_id' => 'required|exists:users_registration,id',
            'role' => 'required|string|in:Vendor',
            'store_name' => 'required|string|unique:store,store_name|max:100',
            'email' => 'required|email|unique:store,email|max:100',
            'phone_number' => 'required|string',
            'location' => 'required|string|max:255',
            'picture' => 'required|image|mimes:jpeg,png,jpg,svg|max:2048',
            'availability' => 'required|in:true,false',
            'longitude' => 'required|string|max:50',
            'latitude' => 'required|string|max:50',
        ]);

        // check if user is authenticated
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        // check if role is vendor
        $vendor = Vendor::where('user_id', $user->id)->exists();

        if (!$vendor) {
            return response()->json(['message' => 'You can not create a store'], 403);
        }

        // upload picture
        $imageName = $request->file('picture')->store('store_pictures', 'public');

        // create new store
        $store = Store::create([
            'user_id' => $user->id,
            'role' => $request->role,
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
            action: 'create_store',
            title: 'Store Created',
            detail: "Store '{$store->store_name}' was created successfully.",
            meta: ['store_id' => $store->id]
        );

        $user->sendNotification(
            type: 'store',
            title: 'Store Created',
            content: "Your store '{$store->store_name}' has been created successfully."
        );

        // return response
        return response()->json(['message' => 'Store created successfully.', 'store' => $store], 200);
    }

    public function updateStore(Request $request, $id)
    {
        // validate the request
        $request->validate([
            'store_name' => 'sometimes|string|unique:store,store_name,' . $id . '|max:100',
            'email' => 'sometimes|email|unique:store,email,' . $id . '|max:100',
            'phone_number' => 'sometimes|string',
            'location' => 'sometimes|string|max:255',
            'picture' => 'sometimes|image|mimes:jpeg,png,jpg,svg|max:2048',
            'availability' => 'sometimes|in:true,false',
            'longitude' => 'sometimes|string|max:50',
            'latitude' => 'sometimes|string|max:50',
        ]);

        // check if user is authenticated
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        // find store
        $store = Store::find($id);
        if (!$store) {
            return response()->json(['message' => 'Store not found.'], 404);
        };

        // check if the authenticated user is not the owner of the store
        if ($store->user_id !== $user->id) {
            return response()->json(['message' => 'You are not authorized to update this store.'], 403);
        };

        // upload picture if exists
        if ($request->hasFile('picture')) {
            // delete old picture
            if ($store->picture) {
                Storage::disk('public')->delete($store->picture);
            }
            $imageName = $request->file('picture')->store('store_pictures', 'public');
            $store->picture = $imageName;
        }

        // update store
        if ($request->has('store_name')) $store->store_name = $request->store_name;
        if ($request->has('email')) $store->email = $request->email;
        if ($request->has('phone_number')) $store->phone_number = $request->phone_number;
        if ($request->has('location')) $store->location = $request->location;
        if ($request->has('availability')) $store->availability = $request->availability;
        if ($request->has('longitude')) $store->longitude = $request->longitude;
        if ($request->has('latitude')) $store->latitude = $request->latitude;
        $store->updated_at = Carbon::now();
        $store->save();

        $user->logActivity(
            action: 'update_store',
            title: 'Store Updated',
            detail: "Store '{$store->store_name}' was updated successfully.",
            meta: ['store_id' => $store->id]
        );

        $user->sendNotification(
            type: 'store',
            title: 'Store Updated',
            content: "Your store '{$store->store_name}' details have been updated."
        );

        // return response
        return response()->json(['message' => 'Store updated successfully.', 'store' => $store]);
    }

    // get vendor stores
    public function getVendorStores()
    {
        // check if user is authenticated
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        //get stores
        $stores = Store::with('ratings:id,rating,comment,rateable_id')
            ->where('role', 'Vendor')
            ->paginate(10);

        // get average rating for each store
        $stores->getCollection()->transform(function ($store) {
            $store->average_rating = $store->averageRating();
            // $store->average_rating = $average ? number_format($average, 1) . ' of 5' : '0 of 5';
            return $store;
        });

        return response()->json(['stores' => $stores], 200);
    }

    // get store by user id
    public function getStoreByUserId($user_id)
    {
        // check if user is authenticated
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        //get store
        $store = Store::with('ratings:id,rating,comment,rateable_id')
            ->where('user_id', $user_id)
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

    // delete store
    public function deleteStore($id)
    {
        try {
            // check if user is authenticated
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            // find the store
            $store = Store::find($id);
            if (!$store) {
                return response()->json(['message' => 'Store not found.'], 404);
            }

            // check if the authenticated user is the owner of the store
            if ($store->user_id !== $user->id) {
                return response()->json(['message' => 'You are not authorized to delete this store.'], 403);
            }

            // delete store picture
            if ($store->picture) {
                Storage::disk('public')->delete($store->picture);
            }

            // get all products related to this store
            $products = Products::where('store_id', $store->id)->get();

            foreach ($products as $product) {
                // delete product images
                if ($product->image1) {
                    Storage::disk('public')->delete($product->image1);
                }
                if ($product->image2) {
                    Storage::disk('public')->delete($product->image2);
                }
                if ($product->image3) {
                    Storage::disk('public')->delete($product->image3);
                }
                if ($product->image4) {
                    Storage::disk('public')->delete($product->image4);
                }

                // delete the product
                $product->delete();
            }


            $user->logActivity(
                action: 'delete_store',
                title: 'Store Deleted',
                detail: "Store '{$store->store_name}' and all related products were deleted.",
                meta: ['store_id' => $store->id]
            );

            $user->sendNotification(
                type: 'store',
                title: 'Store Deleted',
                content: "Your store '{$store->store_name}' and its products have been removed."
            );


            // delete the store itself
            $store->delete();

            // return response
            return response()->json(['message' => 'Store and all related products deleted successfully.'], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while deleting the store.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
