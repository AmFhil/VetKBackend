<?php

namespace App\Http\Controllers;

use App\Models\Products;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ProductsController extends Controller
{
    //add new product
    public function addNewProduct(Request $request)
    {
        try {
            //code...
            // validate the request
            $request->validate([
                'user_id' => 'required|exists:users_registration,id',
                'store_id' => 'required|exists:store,id',
                'product_name' => 'required|string|max:100',
                'category' => 'required|string|max:100',
                'description' => 'required|string|max:1000',
                'tags' => 'required|array',
                'tags.*' => 'string|max:50',
                'location' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'image1' => 'required|image|mimes:jpeg,png,jpg|max:1048',
                'image2' => 'nullable|image|mimes:jpeg,png,jpg|max:1048',
                'image3' => 'nullable|image|mimes:jpeg,png,jpg|max:1048',
                'image4' => 'nullable|image|mimes:jpeg,png,jpg|max:1048',
                'availability' => 'required|in:true,false',
                'available_unit' => 'required|integer|min:0',
            ]);

            // check if user is authenticated
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            // upload images
            $image1Name = $request->file('image1')->store('product_images', 'public');
            $image2Name = $request->file('image2') ? $request->file('image2')->store('product_images', 'public') : null;
            $image3Name = $request->file('image3') ? $request->file('image3')->store('product_images', 'public') : null;
            $image4Name = $request->file('image4') ? $request->file('image4')->store('product_images', 'public') : null;

            // create new product
            $product = Products::create([
                'user_id' => $request->user_id,
                'store_id' => $request->store_id,
                'product_name' => $request->product_name,
                'category' => $request->category,
                'description' => $request->description,
                'tags' => json_encode($request->tags),
                'location' => $request->location,
                'price' => $request->price,
                'image1' => $image1Name,
                'image2' => $image2Name,
                'image3' => $image3Name,
                'image4' => $image4Name,
                'availability' => $request->availability,
                'available_unit' => $request->available_unit,
            ]);

            // return response
            return response()->json(['message' => 'Product created successfully.', 'product' => $product], 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json(['message' => 'An error occurred while creating the product.', 'error' => $th->getMessage()], 500);
        }
    }

    // update product
    public function updateProduct(Request $request, $id)
    {
        try {
            //code...

            // validate the request
            $request->validate([
                'product_name' => 'sometimes|string|max:100',
                'category' => 'sometimes|string|max:100',
                'description' => 'sometimes|string|max:1000',
                'tags' => 'sometimes|array',
                'tags.*' => 'string|max:50',
                'location' => 'sometimes|string|max:255',
                'price' => 'sometimes|numeric|min:0',
                'image1' => 'sometimes|image|mimes:jpeg,png,jpg|max:1048',
                'image2' => 'sometimes|image|mimes:jpeg,png,jpg|max:1048',
                'image3' => 'sometimes|image|mimes:jpeg,png,jpg|max:1048',
                'image4' => 'sometimes|image|mimes:jpeg,png,jpg|max:1048',
                'availability' => 'sometimes|in:true,false',
                'available_unit' => 'sometimes|integer|min:0',
            ]);

            // check if user is authenticated
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            // find the product
            $product = Products::find($id);
            if (!$product) {
                return response()->json(['message' => 'Product not found.'], 404);
            }

            // check if the authenticated user is the owner of the product
            if ($product->user_id !== $user->id) {
                return response()->json(['message' => 'You are not authorized to update this product.'], 403);
            }

            // update images if provided
            if ($request->hasFile('image1')) {
                // delete old image
                if ($product->image1) {
                    File::delete(public_path('storage/' . $product->image1));
                }
                $image1Name = $request->file('image1')->store('product_images', 'public');
                $product->image1 = $image1Name;
            }
            if ($request->hasFile('image2')) {
                // delete old image
                if ($product->image2) {
                    File::delete(public_path('storage/' . $product->image2));
                }
                $image2Name = $request->file('image2')->store('product_images', 'public');
                $product->image2 = $image2Name;
            }
            if ($request->hasFile('image3')) {
                // delete old image
                if ($product->image3) {
                    File::delete(public_path('storage/' . $product->image3));
                }
                $image3Name = $request->file('image3')->store('product_images', 'public');
                $product->image3 = $image3Name;
            }
            if ($request->hasFile('image4')) {
                // delete old image
                if ($product->image4) {
                    File::delete(public_path('storage/' . $product->image4));
                }
                $image4Name = $request->file('image4')->store('product_images', 'public');
                $product->image4 = $image4Name;
            }

            // update other fields
            if ($request->has('product_name')) $product->product_name = $request->product_name;
            if ($request->has('category')) $product->category = $request->category;
            if ($request->has('description')) $product->description = $request->description;
            if ($request->has('tags')) $product->tags = json_encode($request->tags);
            if ($request->has('location')) $product->location = $request->location;
            if ($request->has('price')) $product->price = $request->price;
            if ($request->has('availability')) $product->availability = $request->availability;
            if ($request->has('available_unit')) $product->available_unit = $request->available_unit;

            // save the product
            $product->save();

            // return response
            return response()->json(['message' => 'Product updated successfully.', 'product' => $product], 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json(['message' => 'An error occurred while updating the product.', 'error' => $th->getMessage()], 500);
        }
    }

 //delete product
    public function deleteProduct($id)
    {
        try {
            //code...

            // check if user is authenticated
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            // find the product
            $product = Products::find($id);
            if (!$product) {
                return response()->json(['message' => 'Product not found.'], 404);
            }

            // check if the authenticated user is the owner of the product
            if ($product->user_id !== $user->id) {
                return response()->json(['message' => 'You are not authorized to delete this product.'], 403);
            }

            // delete images
            if ($product->image1) {
                File::delete(public_path('storage/' . $product->image1));
            }
            if ($product->image2) {
                File::delete(public_path('storage/' . $product->image2));
            }
            if ($product->image3) {
                File::delete(public_path('storage/' . $product->image3));
            }
            if ($product->image4) {
                File::delete(public_path('storage/' . $product->image4));
            }

            // delete the product
            $product->delete();

            // return response
            return response()->json(['message' => 'Product deleted successfully.'], 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json(['message' => 'An error occurred while deleting the product.', 'error' => $th->getMessage()], 500);
        }
    }

    // get products by store
    public function getProductsByStore($store_id){
        try {
            //code...

            // check if user is authenticated
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            // get products
            $products = Products::where('store_id', $store_id)->with('ratings:id,rating,comment,rateable_id')->paginate(10);

            // get average rating for each product
            $products->getCollection()->transform(function ($product){
                $product->average_rating = $product->averageRating();
                return $product;
            });

            // return response
            return response()->json(['message' => 'Products retrieved successfully.', 'products' => $products], 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json(['message' => 'An error occurred while retrieving products.', 'error' => $th->getMessage()], 500);
        }
    }
}
