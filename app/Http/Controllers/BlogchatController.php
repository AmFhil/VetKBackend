<?php

namespace App\Http\Controllers;

use App\Models\BlogChat;
use App\Models\BlogChatComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BlogChatController extends Controller
{
    //
    public function addBlog(Request $request)
    {
        $admin = auth('admin')->user();

        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:50',
            'content' => 'required|string|max:1000',
            'category' => 'required|string|max:50',
            'section' => 'required|string|in:hot news,normal post',
            'picture' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        //  Upload picture
        $imageName = null;
        if ($request->hasFile('picture')) {
            $imageName = $request->file('picture')->store('blog_pictures', 'public');
        }

        //  Create unique slug
        $baseSlug = Str::slug($request->title);
        $slug = $baseSlug;
        $count = 1;
        while (BlogChat::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $count++;
        }

        // create new
        $blog = BlogChat::create([
            'user_id' => $admin->id,
            'user_type' => get_class($admin),
            'slug' => $slug,
            'title' => $request->title,
            'content' => $request->content,
            'category' => $request->category,
            'section' => $request->section,
            'picture' => $imageName,
            'status' => 'draft', // default
        ]);

        return response()->json([
            'message' => 'Blog post created successfully (waiting approval)',
            'blog' => $blog,
        ], 201);
    }

    // get all for admin
    public function getAllBlog()
    {
        $admin = auth('admin')->user();

        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $blog = BlogChat::with('blogChatComment')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($blog);
    }

    // publish blog
    public function publish($id)
    {
        $admin = auth('admin')->user();

        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $blog = BlogChat::findOrFail($id);
        $blog->status = 'published';
        $blog->save();

        return response()->json(['message' => 'Blog published Successfully', 'blog' => $blog], 200);
    }

    // archive blog
    public function archive($id)
    {
        $admin = auth('admin')->user();

        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $blog = BlogChat::findOrFail($id);
        $blog->status = 'archived';
        $blog->save();

        return response()->json(['message' => 'Blog archived successfully', 'blog' => $blog], 200);
    }

    // delete blog
    public function delete($id)
    {
        $admin = auth('admin')->user();

        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $blog = BlogChat::findOrFail($id);

        if ($blog->picture && Storage::disk('public')->exists($blog->picture)) {
            Storage::disk('public')->delete($blog->picture);
        }

        $blog->delete();

        return response()->json(['message' => 'Blog deleted successfully']);
    }

    // update blog
    public function updateBlog(Request $request, $id)
    {
        $admin = auth('admin')->user();

        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $blog = BlogChat::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:50',
            'content' => 'sometimes|string|max:1000',
            'category' => 'sometimes|string|max:50',
            'section' => 'sometimes|string|in:hot news,normal post',
            'picture' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        // handle file upload
        if ($request->hasFile('picture')) {
            if ($blog->picture && Storage::disk('public')->exists($blog->picture)) {
                Storage::disk('public')->delete($blog->picture);
            }
            $blog->picture = $request->file('picture')->store('blog_pictures', 'public');
        }

        //  Update other fields
        $blog->fill($request->only(['title', 'content', 'category', 'section']));
        $blog->save();

        return response()->json(['message' => 'Blog updated successfully', 'blog' => $blog]);
    }

    private function loadBlog($query, Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $blog = $query->where('status', 'published')
            ->with(['comments' => function ($q) {
                $q->whereNull('parent_id')
                    ->where('flag', '!=', 'hidden') // hide flagged comments
                    ->with(['replies' => function ($r) {
                        $r->where('flag', '!=', 'hidden');
                    }]);
            }])
            ->firstOrFail();

        $user = auth()->user();
        $cacheKey = $user
            ? "blog_view_{$blog->id}_user_{$user->id}"
            : "blog_view_{$blog->id}_ip_{$request->ip()}";

        if (!cache()->has($cacheKey)) {
            $blog->increment('views_count');
            cache()->put($cacheKey, true, now()->addDay());
        }

        return $blog;
    }


    // display blog for view
    public function show($id, Request $request)
    {
        return response()->json(
            $this->loadBlog(BlogChat::where('id', $id), $request)
        );
    }

    // display using slug
    public function showWithSlug($slug, Request $request)
    {
        return response()->json(
            $this->loadBlog(BlogChat::where('slug', $slug), $request)
        );
    }


    // share blog
    public function share($id)
    {
        $blog = BlogChat::findOrFail($id);
        $blog->increment('shares_count');

        return response()->json(['message' => 'Share recorded']);
    }

    // trending
    public function trending()
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $trending = Cache::remember('trending_blogs', now()->addMinutes(10), function () {
            return BlogChat::where('status', 'published')
                ->withCount(['blogChatComment', 'blogChatLike'])
                ->with(['comments' => function ($q) {
                    $q->whereNull('parent_id')
                        ->where('flag', '!=', 'hidden') // hide flagged comments
                        ->with(['replies' => function ($r) {
                            $r->where('flag', '!=', 'hidden');
                        }]);
                }])
                ->selectRaw('
            (views_count * 0.3) +
            (likes_count * 0.5) +
            (comments_count * 0.7) +
            (shares_count * 1) as score
                                    ')
                ->orderByDesc('score')
                ->take(10)
                ->get();
        });

        return response()->json($trending);
    }



    // Reject (archive) a blog post
    public function reject($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $blog = BlogChat::findOrFail($id);
        $blog->status = 'archived';
        $blog->save();

        return response()->json(['message' => 'Blog post rejected/archived']);
    }

    public function index()
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $blogs = Cache::remember('blogs_index', now()->addMinutes(5), function () {
            return BlogChat::where('status', 'published')
                ->withCount(['blogChatComment', 'blogChatLike'])
                ->with(['comments' => function ($q) {
                    $q->whereNull('parent_id')
                        ->where('flag', '!=', 'hidden') // hide flagged comments
                        ->with(['replies' => function ($r) {
                            $r->where('flag', '!=', 'hidden');
                        }]);
                }])
                ->orderByDesc('created_at')
                ->paginate(10);
        });

        return response()->json($blogs);
    }
}
