<?php

namespace App\Http\Controllers;

use App\Models\Products;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vendor;
use App\Traits\BroadcastsAdminEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ProductsController extends Controller
{
    use BroadcastsAdminEvents;

    private function handleImageUpload(Request $request, Products $product = null)
    {
        $images = [];

        // If new images are uploaded
        if ($request->hasFile('images')) {
            // Delete old images if updating
            if ($product && $product->images) {
                foreach ($product->images as $oldImage) {
                    Storage::disk('public')->delete($oldImage);
                }
            }

            // Store new images
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('product_images', 'public');
            }
        } elseif ($product) {
            // Retain old images if no new ones uploaded
            $images = $product->images ?? [];
        }

        return $images;
    }




    private function generateProductId($userId, $store_id)
    {
        $productCount = Products::where('user_id', $userId)
            ->where('store_id', $store_id)
            ->count();
        $randomNumber = mt_rand(100000, 999999);
        $letter = chr(97 + $productCount);

        return "PT{$randomNumber}{$letter}";
    }


    //add new product
    public function addNewProduct(Request $request)
    {
        try {
            $request->merge([
                'availability' => filter_var($request->availability, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);

            $request->validate([
                'store_id' => 'required|exists:store,id',
                'product_name' => 'required|string|max:100',
                'category' => 'required|string|max:100|exists:categories,name',
                'description' => 'required|string|max:1000',
                'location' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'tags'   => 'required|array',
                'tags.*' => 'string|max:50',
                'images' => 'required|array',
                'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
                'availability' => 'required|boolean',
                'available_unit' => 'required|integer|min:0',
            ]);

            $user = auth('user')->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            // Check if user is a vendor
            $isVendor = Vendor::where('user_id', $user->id)->exists();


            $images = $this->handleImageUpload($request);

            $productId = $this->generateProductId($user->id, $request->store_id);

            $product = Products::create([
                'user_id' => $user->id,
                'store_id' => $request->store_id,
                'product_id' => $productId,
                'product_type' => $isVendor ? 1 : 0,
                'product_name' => $request->product_name,
                'category' => $request->category,
                'description' => $request->description,
                'location' => $request->location,
                'price' => $request->price,
                'availability' => $request->availability,
                'available_unit' => $request->available_unit,
                'images' => $images,
            ]);

            if ($request->has('tags')) {
                $tags = collect($request->tags)->map(function ($tag) {
                    return Tag::firstOrCreate(['name' => $tag])->id;
                });

                $product->tags()->sync($tags);
            }

            // clear related caches
            Cache::forget("products_by_user_{$user->id}");
            Cache::forget("products_by_store_{$request->store_id}");
            Cache::forget("product_not_on_store");

            //  Log activity
            $user->logActivity(
                'product_created',
                'Product Created',
                "A new product '{$product->product_name}' (ID: {$product->product_id}) was created by {$user->first_name}.",
                ['product_id' => $product->id]
            );

            $user->sendNotification(
                'product',
                'Product Created',
                "Your product <strong>{$product->product_name}</strong> (ID: {$product->product_id}) has been successfully added.",
                ['product_id' => $product->id]
            );


            return response()->json(['message' => 'Product created successfully.', 'product' => $product], 201);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'An error occurred while creating the product.', 'error' => $th->getMessage()], 500);
        }
    }

    // update product
    public function updateProduct(Request $request, $id)
    {
        try {
            $request->merge([
                'availability' => filter_var($request->availability, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);

            $request->validate([
                'product_name' => 'sometimes|string|max:100',
                'category' => 'sometimes|string|max:100|exists:categories,name',
                'description' => 'sometimes|string|max:1000',
                'location' => 'sometimes|string|max:255',
                'price' => 'sometimes|numeric|min:0',
                'tags'   => 'sometimes|nullable|array',
                'tags.*' => 'string|max:50',
                'images' => 'sometimes|array',
                'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
                'availability' => 'sometimes|boolean',
                'available_unit' => 'sometimes|integer|min:0',
            ]);

            $user = auth('user')->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $product = Products::find($id);
            if (!$product) {
                return response()->json(['message' => 'Product not found.'], 404);
            }

            if ($product->user_id !== $user->id) {
                return response()->json(['message' => 'You are not authorized to update this product.'], 403);
            }

            // Handle images: delete old ones if replaced, keep if not
            $images = $this->handleImageUpload($request, $product);

            $product->update(array_merge($request->only([
                'product_name',
                'category',
                'description',
                'location',
                'price',
                'availability',
                'available_unit'
            ]), [
                'images' => $images
            ]));

            if ($request->has('tags')) {
                $tags = collect($request->tags)->map(function ($tag) {
                    return Tag::firstOrCreate(['name' => $tag])->id;
                });
                $product->tags()->sync($tags);
            }

            $product->touch();

            // Clear caches
            Cache::forget("product_{$product->id}");
            Cache::forget("products_by_user_{$user->id}");
            Cache::forget("products_by_store_{$product->store_id}");
            Cache::forget("related_products_{$product->id}");

            // Log & notify
            $user->logActivity(
                'product_updated',
                'Product Updated',
                "Product '{$product->product_name}' (ID: {$product->product_id}) was updated by {$user->first_name}.",
                ['product_id' => $product->id]
            );

            $user->sendNotification(
                'product',
                'Product Updated',
                "Your product <strong>{$product->product_name}</strong> (ID: {$product->product_id}) has been updated successfully.",
                ['product_id' => $product->id]
            );

            return response()->json(['message' => 'Product updated successfully.', 'product' => $product], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while updating the product.',
                'error' => $th->getMessage()
            ], 500);
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

            // Delete stored images (all paths from array)
            foreach ($product->images ?? [] as $path) {
                Storage::disk('public')->delete($path);
            }

            $name = $product->product_name;

            $product->tags()->detach();
            $product->delete();

            // clear related caches
            Cache::forget("product_{$product->id}");
            Cache::forget("products_by_user_{$user->id}");
            Cache::forget("products_by_store_{$product->store_id}");
            Cache::forget("related_products_{$product->id}");

            // Log activity
            $user->logActivity(
                'product_deleted',
                'Product Deleted',
                "Product '{$name}' (ID: {$product->product_id}) was deleted by {$user->first_name}.",
                ['product_id' => $id]
            );

            $user->sendNotification(
                'product',
                'Product Deleted',
                "Your product <strong>{$name}</strong> (ID: {$product->product_id}) has been permanently deleted.",
                ['product_id' => $id]
            );

            return response()->json(['message' => 'Product deleted successfully.'], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while deleting the product.',
                'error' => $th->getMessage()
            ], 500);
        }
    }


    // get products by store
    public function getProductsByStore($store_id)
    {
        try {
            $user = auth('user')->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $products = Cache::remember("products_by_store_{$store_id}", now()->addMinutes(15), function () use ($store_id) {
                $products = Products::where('store_id', $store_id)
                    ->with('ratings:id,rating,comment,rateable_id', 'tags')
                    ->paginate(10);

                // Add average rating for each product
                $products->getCollection()->transform(function ($p) {
                    $p->average_rating = $p->averageRating();
                    return $p;
                });

                return $products;
            });

            return response()->json(['message' => 'Products retrieved successfully.', 'products' => $products], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'An error occurred while retrieving products.', 'error' => $th->getMessage()], 500);
        }
    }

    // get product by store-id for customers
    public function getProductsByStoreForCustomers($store_id)
    {
        try {
            $user = auth('user')->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $products = Cache::remember("products_by_store_{$store_id}", now()->addMinutes(15), function () use ($store_id) {
                $products = Products::where('store_id', $store_id)
                    ->where('availability', true)
                    ->where('disabled', false)
                    ->where('available_unit', '>', 0)
                    ->with('ratings:id,rating,comment,rateable_id', 'tags')
                    ->paginate(10);

                // Add average rating for each product
                $products->getCollection()->transform(function ($p) {
                    $p->average_rating = $p->averageRating();
                    return $p;
                });

                return $products;
            });

            return response()->json(['message' => 'Products retrieved successfully.', 'products' => $products], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'An error occurred while retrieving products.', 'error' => $th->getMessage()], 500);
        }
    }

    // get all product related to a user_id
    public function getProductByUserId($user_id)
    {
        try {
            $user = auth('user')->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $products = Cache::remember("products_by_user_{$user_id}", now()->addMinutes(15), function () use ($user_id) {
                $products = Products::where('user_id', $user_id)
                    ->with('ratings:id,rating,comment,rateable_id', 'tags')
                    ->paginate(10);

                $products->getCollection()->transform(function ($p) {
                    $p->average_rating = $p->averageRating();
                    return $p;
                });

                return $products;
            });

            return response()->json(['message' => 'Products retrieved successfully.', 'products' => $products], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'An error occurred while retrieving user products.', 'error' => $th->getMessage()], 500);
        }
    }

    // get product by product type
    public function getProductNotOnStore()
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $products = Cache::remember("product_not_on_store", now()->addMinutes(15), function () {
                $products = Products::where('product_type', 0)
                    ->with('ratings:id,rating,comment,rateable_id', 'tags')
                    ->where('availability', true)
                    ->where('disabled', false)
                    ->where('available_unit', '>', 0)
                    ->paginate(10);

                $products->getCollection()->transform(function ($p) {
                    $p->average_rating = $p->averageRating();
                    return $p;
                });

                return $products;
            });

            return response()->json(['message' => 'Products retrieved successfully.', 'products' => $products], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'An error occurred while retrieving products.', 'error' => $th->getMessage()], 500);
        }
    }

    // search product
    public function searchProducts(Request $request)
    {
        try {
            $request->validate([
                'category' => 'nullable|string|max:100',
                'keyword'  => 'nullable|string|max:100',
            ]);

            $query = Products::with('ratings:id,rating,comment,rateable_id', 'tags')
                ->where('availability', true)
                ->where('disabled', false)
                ->where('available_unit', '>', 0);

            if ($request->filled('category')) {
                $query->where('category', 'LIKE', "%{$request->category}%");
            }

            if ($request->filled('keyword')) {
                $query->where('description', 'LIKE', "%{$request->keyword}%")
                    ->orWhere('product_name', 'LIKE', "%{$request->keyword}%");
            }

            $products = $query->paginate(10);

            $products->getCollection()->transform(function ($product) {
                $product->average_rating = $product->averageRating();
                return $product;
            });

            return response()->json(['message' => 'Products retrieved successfully.', 'products' => $products], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'An error occurred while searching.', 'error' => $th->getMessage()], 500);
        }
    }

    public function getRelatedProducts($id)
    {
        try {
            $product = Products::find($id);

            if (!$product) {
                return response()->json(['message' => 'Product not found.'], 404);
            }

            // Cache the related products for performance
            $relatedProducts = Cache::remember("related_products_{$id}", now()->addMinutes(15), function () use ($product, $id) {
                $query = Products::with('ratings:id,rating,comment,rateable_id', 'tags')
                    ->where('category', 'LIKE', "%{$product->category}%")
                    ->where('availability', true)
                    ->where('disabled', false)
                    ->where('available_unit', '>', 0)
                    ->where('id', '!=', $id);

                // If product has tags, use them to improve relevance
                if ($product->tags()->exists()) {
                    $tagIds = $product->tags->pluck('id');
                    $query->whereHas('tags', function ($q) use ($tagIds) {
                        $q->whereIn('tags.id', $tagIds);
                    });
                }

                return $query->limit(10)->get();
            });

            return response()->json([
                'message' => 'Related products retrieved successfully.',
                'products' => $relatedProducts
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Error fetching related products.', 'error' => $th->getMessage()], 500);
        }
    }

    public function disableProduct($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $product = Products::findOrFail($id);
        $user = User::find($product->user_id);

        if ($user) {
            $product->disabled = true;
            $product->save();
            // Notify the user
            $user->sendNotification(
                'product',
                'Product Disabled',
                "Your product <strong>{$product->product_name}</strong> (ID: {$product->product_id}) has been disabled by an administrator. Please contact support for assistance.",
                ['product_id' => $product->id, 'user_id' => $user->id]
            );

            $user->logActivity(
                'product_disabled',
                'Product Disabled',
                "Your product '{$product->product_name}' (ID: {$product->product_id}) was disabled by an admin.",
                ['product_id' => $product->id]
            );

            $user->notify(new \App\Notifications\AdminActionNotification(
                'Product Disabled by Administrator',
                'Dear ' . $user->first_name . ', we regret to inform you that your product "' . $product->product_name . '" (ID: ' . $product->product_id . ') has been disabled by an administrator. Please contact support if you believe this is an error or wish to appeal.'
            ));

            // Admin-facing
            $this->createAdminActivity(
                $admin->id,
                'product_disabled',
                'Product Disabled',
                "Admin {$admin->name} disabled product '{$product->product_name}' (ID: {$product->product_id}) belonging to {$user->first_name}.",
                ['product_id' => $product->id, 'user_id' => $user->id, 'staff_id' => $admin->staff_id]
            );
        }

        Cache::forget("product_{$product->id}");
        Cache::forget("products_by_user_{$user->id}");
        Cache::forget("products_by_store_{$product->store_id}");
        Cache::forget("related_products_{$product->id}");

        return response()->json(['message' => 'Product disabled'], 200);
    }

    public function getProductById($id)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $product = Cache::remember("product_{$id}", now()->addMinutes(15), function () use ($id) {
                $product = Products::where('id', $id)
                    ->with('ratings:id,rating,comment,rateable_id', 'tags')
                    ->first();

                if ($product) {
                    $product->average_rating = $product->averageRating();
                }

                return $product;
            });

            if (!$product) {
                return response()->json(['message' => 'Product not found.'], 404);
            }

            return response()->json(['message' => 'Product retrieved successfully.', 'product' => $product], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'An error occurred while retrieving the product.', 'error' => $th->getMessage()], 500);
        }
    }
}
