<?php

namespace App\Http\Controllers;

use App\Models\Categories;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    // Create a new category
    public function store(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'image' => 'nullable|image|max:2048|jpeg,png,jpg',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
        }

        $category = Categories::create([
            'name' => $validated['name'],
            'image' => $imagePath,
            'created_at' => Carbon::now()
        ]);

        return response()->json($category, 201);
    }

    // Get all categories
    public function index()
    {
        $cacheKey = 'Categories';
        $categories = Cache::remember($cacheKey, now()->addMinutes(30), function () {
            return Categories::all();
        });
    
        return response()->json(['categories' => $categories], 201);
    }

    // Get all categories
    public function adminCategories()
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $cacheKey = 'admin_categories';
        $categories = Cache::remember($cacheKey, now()->addMinutes(30), function () {
            return Categories::all();
        });

        return response()->json(['categories' => $categories], 201);
    }

    // Get a single category
    public function show($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $category = Categories::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        return response()->json($category);
    }

    // Update a category
    public function update(Request $request, $id)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $category = Categories::where('id', $id)->first();
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:50|unique:categories,name',
            'image' => 'nullable|image|max:1048|jpeg,png,jpg',
        ]);

        $imagePath = $category->image;
        if ($request->hasFile('image')) {
            File::delete(storage_path('app/pubic/' . $category->image));
            $imagePath = $request->file('image')->store('categories', 'public');
        }

        $category->update(['name' => $validated['name'] ?? $category->name, 'image' => $imagePath]);

        return response()->json(['category' => $category], 201);
    }

    // Delete a category
    public function destroy($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $category = Categories::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        File::delete(storage_path('app/pubic/' . $category->image));
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }
}
