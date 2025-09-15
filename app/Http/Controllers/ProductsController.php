<?php

namespace App\Http\Controllers;

use App\Models\Products;
use App\Models\Tag;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ProductsController extends Controller
{
    private function handleImageUpload(Request $request, Products $product = null)
    {
        $images = [];
        foreach (['image1', 'image2', 'image3', 'image4'] as $imageField) {
            if ($request->hasFile($imageField)) {
                // Delete old image if updating
                if ($product && $product->$imageField) {
                    Storage::disk('public')->delete($product->$imageField);
                }
                $images[$imageField] = $request->file($imageField)->store('product_images', 'public');
            } elseif ($product) {
                $images[$imageField] = $product->$imageField; // retain old if not updating
            }
        }
        return $images ?? [];
    }

    private function generateProductId($userId)
    {
        $farmCount = Products::where('user_id', $userId)->count();
        $randomNumber = mt_rand(100000, 999999);
        $letter = chr(97 + $farmCount);

        return "FM{$randomNumber}{$letter}";
    }


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
            // $image1Name = $request->file('image1')->store('product_images', 'public');
            // $image2Name = $request->file('image2')?->store('product_images', 'public');
            // $image3Name = $request->file('image3')?->store('product_images', 'public');
            // $image4Name = $request->file('image4')?->store('product_images', 'public');

            $images = $this->handleImageUpload($request);
            $productId = $this->generateProductId($user->id);

            // Create new product
            // $product = Products::create([
            //     'user_id' => $user->id, // always take from auth
            //     'store_id' => $request->store_id,
            //     'product_type' => $isVendor ? 1 : 0,
            //     'product_name' => $request->product_name,
            //     'category' => $request->category,
            //     'description' => $request->description,
            //     'location' => $request->location,
            //     'price' => $request->price,
            //     'image1' => $image1Name,
            //     'image2' => $image2Name,
            //     'image3' => $image3Name,
            //     'image4' => $image4Name,
            //     'availability' => $request->availability,
            //     'available_unit' => $request->available_unit,
            // ]);
            $product = Products::create(array_merge([
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
            ], $images));

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
                'create_product',
                'Product Created',
                "Product {$product->product_name} was created.",
                ['product_id' => $product->id]
            );

            //  Send notification
            $user->sendNotification(
                'product',
                'New Product Added',
                "Your product '{$product->product_name}' has been successfully added.",
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
            $request->validate([
                'product_name' => 'sometimes|string|max:100',
                'category' => 'sometimes|string|max:100',
                'description' => 'sometimes|string|max:1000',
                'location' => 'sometimes|string|max:255',
                'price' => 'sometimes|numeric|min:0',
                'tags'   => 'sometimes|nullable|array',
                'tags.*' => 'string|max:50',
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
            // foreach (['image1', 'image2', 'image3', 'image4'] as $imageField) {
            //     if ($request->hasFile($imageField)) {
            //         if ($product->$imageField) {
            //             Storage::disk('public')->delete($product->$imageField);
            //         }
            //         $product->$imageField = $request->file($imageField)->store('product_images', 'public');
            //     }
            // }

            $images = $this->handleImageUpload($request, $product);

            // Update other fields
            // $fields = ['product_name', 'category', 'description', 'location', 'price', 'availability', 'available_unit'];
            // foreach ($fields as $field) {
            //     if ($request->has($field)) {
            //         $product->$field = $request->$field;
            //     }
            // }



            $product->update(array_merge($request->only([
                'product_name',
                'category',
                'description',
                'location',
                'price',
                'availability',
                'available_unit'
            ]), $images));

            if ($request->has('tags')) {
                $tags = collect($request->tags)->map(function ($tag) {
                    return Tag::firstOrCreate(['name' => $tag])->id;
                });

                $product->tags()->sync($tags);
            }
            $product->touch();

            $product->save();

            // clear related caches
            Cache::forget("product_{$product->id}");
            Cache::forget("products_by_user_{$user->id}");
            Cache::forget("products_by_store_{$product->store_id}");
            Cache::forget("related_products_{$product->id}");

            //  Log activity
            $user->logActivity(
                'update_product',
                'Product Updated',
                "Product {$product->product_name} was updated.",
                ['product_id' => $product->id]
            );

            //  Send notification
            $user->sendNotification(
                'product',
                'Product Updated',
                "Your product '{$product->product_name}' has been updated successfully.",
                ['product_id' => $product->id]
            );

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

            $name = $product->product_name;

            $product->tags()->detach();
            $product->delete();

            // clear related caches
            Cache::forget("product_{$product->id}");
            Cache::forget("products_by_user_{$user->id}");
            Cache::forget("products_by_store_{$product->store_id}");
            Cache::forget("related_products_{$product->id}");

            //  Log activity
            $user->logActivity(
                'delete_product',
                'Product Deleted',
                "Product {$name} was deleted.",
                ['product_id' => $id]
            );

            //  Send notification
            $user->sendNotification(
                'product',
                'Product Deleted',
                "Your product {$name} has been deleted.",
                ['product_id' => $id]
            );

            return response()->json(['message' => 'Product deleted successfully.'], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'An error occurred while deleting the product.', 'error' => $th->getMessage()], 500);
        }
    }

    // get products by store
    public function getProductsByStore($store_id)
    {
        try {
            $user = auth()->user();
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

    // get all product related to a user_id
    public function getProductByUserId($user_id)
    {
        try {
            $user = auth()->user();
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

            $query = Products::with('ratings:id,rating,comment,rateable_id', 'tags');

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
}
