<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    //
    public function addNewStore(Request $request){
        // validate the request
        $request->validate([
            'user_id' => 'required|exists:users_registration,id',
            'role' => 'required|string|in:Veterinarian,Veterinary Paraprofessional,Veterinary Clinic,Pet Owner,Livestock Farmer,Vendor,Others',
            'store_name' => 'required|string|unique:store,store_name|max:100',
            'email' => 'required|email|unique:store,email|max:100',
            'phone_number' => 'required|string',
            'location' => 'required|string|max:255',
            'picture' => 'required|image|mimes:jpeg,png,jpg,svg|max:1048',
            'availability' => 'required|in:true,false',
            'longitude' => 'required|string|max:50',
            'latitude' => 'required|string|max:50',
        ]);

        // check if user is authenticated
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        // upload picture
        $imageName = $request->file('picture')->store('store_pictures', 'public');


        // create new store
        $store = Store::create([
            'user_id' => $request->user_id,
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

        // return response
        return response()->json(['message' => 'Store created successfully.', 'store' => $store], 200);

    }

    // get store by user id
    public function getStoreByUserId($user_id){
        // check if user is authenticated
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        //get store
        $store = Store::with('ratings:id,rating,comment,rateable_id')
            ->where('user_id', $user_id)
            ->paginate(10);


        if(!$store){
            return response()->json(['message' => 'Store not found.'], 404);
        }

        // get average rating
        $store->getCollection()->transform(function ($store){
            $store->average_rating = $store->averageRating();
            // $store->average_rating = $average ? number_format($average, 1) . ' of 5' : '0 of 5';
            return $store;
        });

        return response()->json(['store' => $store], 200);
    }

    // get store by store id
    public function getStoreById($id){
        // check if user is authenticated
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        //get store
        $store = Store::with('ratings:id,rating,comment,rateable_id')
            ->where('id', $id)
            ->first();

        if(!$store){
            return response()->json(['message' => 'Store not found.'], 404);
        };

        // get average rating
        $store->average_rating = $store->averageRating();

        return response()->json(['store' => $store], 200);
    }
}
