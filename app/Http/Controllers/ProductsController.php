<?php

namespace App\Http\Controllers;

use App\Models\Products;
use App\Models\Tag;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ProductsController extends Controller
{
    //add new product
    public function addNewProduct(Request $request)
    {
        try {
            $request->validate([
                'store_id' => 'nullable|exists:store,id',
                'product_name' => 'required|string|max:100',
                'category' => 'required|string|max:100',
                'description' => 'required|string|max:1000',
                'location' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'tags'   => 'required|array',
                'tags.*' => 'string|max:50',
                'image1' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'image2' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'image3' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'image4' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'availability' => 'required|boolean',
                'available_unit' => 'required|integer|min:0',
            ]);

            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            // Check if user is a vendor
            $isVendor = Vendor::where('user_id', $user->id)->exists();

            // Upload images
            $image1Name = $request->file('image1')->store('product_images', 'public');
            $image2Name = $request->file('image2')?->store('product_images', 'public');
            $image3Name = $request->file('image3')?->store('product_images', 'public');
            $image4Name = $request->file('image4')?->store('product_images', 'public');

            // Create new product
            $product = Products::create([
                'user_id' => $user->id, // always take from auth
                'store_id' => $request->store_id,
                'product_type' => $isVendor ? 1 : 0,
                'product_name' => $request->product_name,
                'category' => $request->category,
                'description' => $request->description,
                'location' => $request->location,
                'price' => $request->price,
                'tags'   => 'nullable|array',
                'tags.*' => 'string|max:50',
                'image1' => $image1Name,
                'image2' => $image2Name,
                'image3' => $image3Name,
                'image4' => $image4Name,
                'availability' => $request->availability,
                'available_unit' => $request->available_unit,
            ]);

            if ($request->has('tags')) {
                $tags = collect($request->tags)->map(function ($tag) {
                    return Tag::firstOrCreate(['name' => $tag])->id;
                });

                $product->tags()->sync($tags);
            }

            return response()->json(['message' => 'Product created successfully.', 'product' => $product], 201);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'An error occurred while creating the product.', 'error' => $th->getMessage()], 500);
        }
    }

    // update product
    public function updateProduct(Request $request, $id)
    {
        try {
            $request->validate([
                'product_name' => 'sometimes|string|max:100',
                'category' => 'sometimes|string|max:100',
                'description' => 'sometimes|string|max:1000',
                'location' => 'sometimes|string|max:255',
                'price' => 'sometimes|numeric|min:0',
                'image1' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
                'image2' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
                'image3' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
                'image4' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
                'availability' => 'sometimes|boolean',
                'available_unit' => 'sometimes|integer|min:0',
            ]);

            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $product = Products::find($id);
            if (!$product) {
                return response()->json(['message' => 'Product not found.'], 404);
            }

            // Only the owner of the product can update
            if ($product->user_id !== $user->id) {
                return response()->json(['message' => 'You are not authorized to update this product.'], 403);
            }

            // Handle image replacements
            foreach (['image1', 'image2', 'image3', 'image4'] as $imageField) {
                if ($request->hasFile($imageField)) {
                    if ($product->$imageField) {
                        Storage::disk('public')->delete($product->$imageField);
                    }
                    $product->$imageField = $request->file($imageField)->store('product_images', 'public');
                }
            }

            // Update other fields
            $fields = ['product_name', 'category', 'description', 'location', 'price', 'availability', 'available_unit'];
            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $product->$field = $request->$field;
                }
            }

            if ($request->has('tags')) {
                $tags = collect($request->tags)->map(function ($tag) {
                    return Tag::firstOrCreate(['name' => $tag])->id;
                });

                $product->tags()->sync($tags);
            }

            $product->save();

            return response()->json(['message' => 'Product updated successfully.', 'product' => $product], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'An error occurred while updating the product.', 'error' => $th->getMessage()], 500);
        }
    }

    //delete product
    public function deleteProduct($id)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $product = Products::find($id);
            if (!$product) {
                return response()->json(['message' => 'Product not found.'], 404);
            }

            if ($product->user_id !== $user->id) {
                return response()->json(['message' => 'You are not authorized to delete this product.'], 403);
            }

            // Delete stored images
            foreach (['image1', 'image2', 'image3', 'image4'] as $imageField) {
                if ($product->$imageField) {
                    Storage::disk('public')->delete($product->$imageField);
                }
            }

            $product->delete();

            return response()->json(['message' => 'Product deleted successfully.'], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'An error occurred while deleting the product.', 'error' => $th->getMessage()], 500);
        }
    }

    // get products by store
    public function getProductsByStore($store_id)
    {
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
            $products->getCollection()->transform(function ($product) {
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
