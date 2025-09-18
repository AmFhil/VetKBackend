<?php

namespace App\Http\Controllers;

use App\Models\BlogChat;
use App\Traits\BroadcastsAdminEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BlogChatController extends Controller
{
    use BroadcastsAdminEvents;

    /**
     * Clear blog-related caches.
     */
    private function clearBlogCaches($blogId = null): void
    {
        Cache::forget('blogs_index');
        Cache::forget('trending_blogs');

        if ($blogId) {
            Cache::forget("blog_show_{$blogId}");
        }
    }

    /**
     * Create a new blog post (Admin only).
     */
    public function addBlog(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $request->validate([
            'title'    => 'required|string|max:50',
            'content'  => 'required|string|max:1000',
            'category' => 'required|string|max:50',
            'section'  => 'required|string|in:hot news,normal post',
            'picture'  => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $imageName = $request->hasFile('picture')
            ? $request->file('picture')->store('blog_pictures', 'public')
            : null;

        $baseSlug = Str::slug($request->title);
        $slug = $baseSlug;
        $count = 1;
        while (BlogChat::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $count++;
        }

        $blog = BlogChat::create([
            'user_id'   => $admin->id,
            'user_type' => get_class($admin),
            'slug'      => $slug,
            'title'     => $request->title,
            'content'   => $request->content,
            'category'  => $request->category,
            'section'   => $request->section,
            'picture'   => $imageName,
            'status'    => 'draft',
        ]);

        $this->clearBlogCaches();

        $this->createAdminActivity(
            $admin->id,
            'created',
            'New Blog Created',
            "Admin {$admin->first_name} created a blog titled '{$request->title}'",
            ['blog_id' => $blog->id]
        );

        $this->createAdminNotification(
            $admin->id,
            'Blog Post Pending Approval',
            'blog',
            "Your blog '{$request->title}' is created and waiting approval.",
            ['blog_id' => $blog->id]
        );

        return response()->json([
            'message' => 'Blog post created successfully (waiting approval)',
            'blog'    => $blog,
        ], 201);
    }

    /**
     * Get all blogs (Admin only).
     */
    public function getAllBlog()
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $blogs = BlogChat::with([
            'user',
            'comments.user',
            'comments.replies.user',
        ])->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($blogs);
    }

    /**
     * Publish a blog (Admin).
     */
    public function publish($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $blog = BlogChat::findOrFail($id);
        $blog->update(['status' => 'published']);

        $this->clearBlogCaches($blog->id);

        $this->createAdminActivity(
            $admin->id,
            'published',
            "Blog Published",
            "Admin {$admin->first_name} published blog '{$blog->title}'.",
            ['blog_id' => $blog->id]
        );

        $this->createAdminNotification(
            $admin->id,
            "Blog Published",
            'blog',
            "Blog '{$blog->title}' is now live!",
            ['blog_id' => $blog->id]
        );

        return response()->json(['message' => 'Blog published successfully', 'blog' => $blog], 200);
    }

    /**
     * Archive a blog (Admin).
     */
    public function archive($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $blog = BlogChat::findOrFail($id);
        $blog->update(['status' => 'archived']);

        $this->clearBlogCaches($blog->id);

        $this->createAdminActivity(
            $admin->id,
            'archived',
            "Blog Archived",
            "Admin {$admin->first_name} archived blog '{$blog->title}'.",
            ['blog_id' => $blog->id]
        );

        return response()->json(['message' => 'Blog archived successfully', 'blog' => $blog], 200);
    }

    /**
     * Reject a blog (Admin).
     */
    public function reject($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $blog = BlogChat::findOrFail($id);
        $blog->update(['status' => 'archived']);

        $this->clearBlogCaches($blog->id);

        $this->createAdminActivity(
            $admin->id,
            'rejected',
            "Blog Rejected",
            "Admin {$admin->first_name} rejected blog '{$blog->title}' (moved to archived).",
            ['blog_id' => $blog->id]
        );

        return response()->json(['message' => 'Blog rejected/archived']);
    }

    /**
     * Delete a blog (Admin).
     */
    public function delete($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $blog = BlogChat::findOrFail($id);

        if ($blog->picture && Storage::disk('public')->exists($blog->picture)) {
            Storage::disk('public')->delete($blog->picture);
        }

        $title = $blog->title;
        $blog->delete();

        $this->clearBlogCaches($id);

        $this->createAdminActivity(
            $admin->id,
            'deleted',
            "Blog Deleted",
            "Admin {$admin->first_name} permanently deleted blog '{$title}'.",
            ['blog_id' => $id]
        );

        return response()->json(['message' => 'Blog deleted successfully']);
    }

    /**
     * Update a blog (Admin).
     */
    public function updateBlog(Request $request, $id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $blog = BlogChat::findOrFail($id);

        $request->validate([
            'title'    => 'sometimes|string|max:50',
            'content'  => 'sometimes|string|max:1000',
            'category' => 'sometimes|string|max:50',
            'section'  => 'sometimes|string|in:hot news,normal post',
            'picture'  => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($request->hasFile('picture')) {
            if ($blog->picture && Storage::disk('public')->exists($blog->picture)) {
                Storage::disk('public')->delete($blog->picture);
            }
            $blog->picture = $request->file('picture')->store('blog_pictures', 'public');
        }

        $blog->fill($request->only(['title', 'content', 'category', 'section']))->save();

        $this->clearBlogCaches($blog->id);

        $this->createAdminActivity(
            $admin->id,
            'updated',
            "Blog Updated",
            "Admin {$admin->first_name} updated blog '{$blog->title}'.",
            ['blog_id' => $blog->id]
        );

        return response()->json(['message' => 'Blog updated successfully', 'blog' => $blog]);
    }

    /**
     * Display a blog by ID (User).
     */
    public function show($id, Request $request)
    {
        return response()->json(
            $this->loadBlog(BlogChat::where('id', $id), $request)
        );
    }

    /**
     * Display a blog by slug (User).
     */
    public function showWithSlug($slug, Request $request)
    {
        return response()->json(
            $this->loadBlog(BlogChat::where('slug', $slug), $request)
        );
    }

    /**
     * Share a blog (User).
     */
    public function share($id)
    {
        $blog = BlogChat::findOrFail($id);
        $blog->increment('shares_count');

        $this->clearBlogCaches($id);

        return response()->json(['message' => 'Share recorded']);
    }

    /**
     * Trending blogs (User).
     */
    public function trending()
    {
        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $trending = Cache::remember('trending_blogs', now()->addMinutes(10), function () {
            return BlogChat::where('status', 'published')
                ->withCount(['comments', 'likes'])
                ->with([
                    'user',
                    'comments.user',
                    'comments.replies.user',
                ])
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

    /**
     * Index of blogs (User).
     */
    public function index()
    {
        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $blogs = Cache::remember('blogs_index', now()->addMinutes(5), function () {
            return BlogChat::where('status', 'published')
                ->withCount(['comments', 'likes'])
                ->with([
                    'user',
                    'comments.user',
                    'comments.replies.user',
                ])
                ->orderByDesc('created_at')
                ->paginate(10);
        });

        return response()->json($blogs);
    }

    /**
     * Private helper to load blog with comments & track views.
     */
    private function loadBlog($query, Request $request)
    {
        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $blog = $query->where('status', 'published')
            ->with([
                'user',
                'comments' => function ($q) {
                    $q->whereNull('parent_id')
                        ->where('flag', '!=', 'hidden')
                        ->with([
                            'user',
                            'replies' => function ($r) {
                                $r->where('flag', '!=', 'hidden')->with('user');
                            }
                        ]);
                }
            ])
            ->firstOrFail();

        $cacheKey = $user
            ? "blog_view_{$blog->id}_user_{$user->id}"
            : "blog_view_{$blog->id}_ip_{$request->ip()}";

        if (!cache()->has($cacheKey)) {
            $blog->increment('views_count');
            cache()->put($cacheKey, true, now()->addDay());
        }

        return $blog;
    }
}
