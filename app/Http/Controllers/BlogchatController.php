<?php

namespace App\Http\Controllers;

use App\Models\BlogChat;
use App\Models\BlogChatLike;
use App\Models\Membership;
use App\Traits\BroadcastsAdminEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BlogChatController extends Controller
{
    use BroadcastsAdminEvents;


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

        $baseSlug = Str::slug($request->title) . '-' . uniqid();
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

        Membership::firstOrCreate([
            'member_id'   => $admin->id,
            'member_type' => get_class($admin),
            'joinable_id' => $blog->id,
            'joinable_type' => BlogChat::class,
        ]);



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
            'comments.user',
            'comments.replies.user',
            'likers'
        ])->orderBy('created_at', 'desc')->paginate(10);

        $blogs->through(function ($chat) use ($admin) {
            $chat->has_liked = $chat->likers->contains(function ($like) use ($admin) {
                return $like->user_id === $admin->id && $like->user_type === get_class($admin);
            });

            return $chat;
        });

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


        $title = $blog->title;
        $blog->delete();

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
     * get deleted blog (Admin).
     */
    public function getDeletedBlogs()
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $blogs = BlogChat::onlyTrashed()
            ->with([
                'comments.user',
                'comments.replies.user',
                'likers'
            ])
            ->orderBy('deleted_at', 'desc')
            ->paginate(10);

        $blogs->through(function ($chat) use ($admin) {
            $chat->has_liked = $chat->likers->contains(function ($like) use ($admin) {
                return $like->user_id === $admin->id && $like->user_type === get_class($admin);
            });

            return $chat;
        });

        return response()->json($blogs);
    }

    /**
     * Restore a blog (Admin).
     */
    public function restore($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $blog = BlogChat::withTrashed()->findOrFail($id);
        $blog->restore();

        $this->createAdminActivity(
            $admin->id,
            'restored',
            "Blog Restored",
            "Admin {$admin->first_name} restored blog '{$blog->title}'.",
            ['blog_id' => $blog->id]
        );

        return response()->json(['message' => 'Blog restored successfully', 'blog' => $blog]);
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
    public function show($identifier, Request $request)
    {
        if ($identifier === 'undefined' || !is_numeric($identifier)) {  // Adjust if slug
            return response()->json(['message' => 'Invalid blog identifier'], 400);
        }
        return response()->json(
            $this->loadBlog(BlogChat::where('id', $identifier), $request, $identifier)
        );
    }

    /**
     * Display a blog by slug (User).
     */
    public function showWithSlug($slug, Request $request)
    {
        if ($slug === 'undefined') {  // Adjust if slug
            return response()->json(['message' => 'Invalid blog identifier'], 400);
        }
        return response()->json(
            $this->loadBlog(BlogChat::where('slug', $slug), $request, $slug)
        );
    }

    /**
     * Share a blog (User).
     */
    public function share($id)
    {
        $blog = BlogChat::findOrFail($id);
        $blog->increment('shares_count');

        Cache::forget("blog_show_{$blog->id}");
        Cache::forget("trending_blogs");

        $registry = Cache::get('blog_cache_keys', []);

        foreach ($registry as $key) {
            if (str_starts_with($key, 'blogs_index_') || str_starts_with($key, 'blog_show_') || str_starts_with($key, 'trending_blogs')) {
                Cache::forget($key);
            }
        }

        // Reset the registry
        Cache::forget('blog_cache_keys');

        return response()->json(['message' => 'Share recorded']);
    }

    /**
     * Trending blogs (User).
     */
    public function trending()
    {
        $user = auth('user')->user() ?: auth('admin')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $trending = Cache::remember('trending_blogs', now()->addMinutes(10), function () use ($user) {
            return BlogChat::where('status', 'published')
                ->withCount(['comments', 'likes'])
                ->with([
                    'likers'
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

        $blogChat = $trending->map(function ($blog) use ($user) {
            $blog->has_liked = $blog->likers->contains(function ($like) use ($user) {
                return ((int) $like->user_id === (int) $user->id) && ($like->user_type === get_class($user));
            });

            return $blog;
        });
        return response()->json($blogChat);
    }

    /**
     * Index of blogs (User).
     */
    public function index(Request $request)
    {
        $user = auth('user')->user() ?: auth('admin')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $cacheKey = "blogs_index_" . class_basename(get_class($user)) . "_{$user->id}";

        $blogs = $this->rememberWithRegistry($cacheKey, function () use ($user) {
            return BlogChat::where('status', 'published')
                ->withCount(['comments', 'likes'])
                ->with([
                    'likers'
                ])
                ->orderByDesc('created_at')
                ->paginate(10);
        });

        $blogChat = $blogs->through(function ($blog) use ($user) {
            $blog->has_liked = $blog->likers->contains(function ($like) use ($user) {
                return ((int) $like->user_id === (int) $user->id) && ($like->user_type === get_class($user));
            });

            return $blog;
        });
        return response()->json($blogChat);
    }


    /**
     * Private helper to load blog with comments & track views.
     */
    private function loadBlog($query, Request $request, $identifies)
    {
        $user = auth('user')->user() ?: auth('admin')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        if ($identifies === 'undefined' || empty($identifies)) {
            return response()->json(['message' => 'Invalid identifier'], 400);
        }

        $cacheKey = "blog_show_{$identifies}_" . class_basename(get_class($user)) . "_{$user->id}";

        $blog = $this->rememberWithRegistry($cacheKey, function () use ($query) {
            return $query->where('status', 'published')
                ->with([
                    'comments' => function ($q) {
                        $q->whereNull('parent_id')
                            ->where('flag', '!=', 'hidden')
                            ->with([
                                'replies' => function ($r) {
                                    $r->where('flag', '!=', 'hidden');
                                }
                            ]);
                    },
                    'likers'
                ])
                ->firstOrFail();
        });

        $blog->has_liked = $blog->likers->contains(function ($like) use ($user) {
            return ((int) $like->user_id === (int) $user->id) && ($like->user_type === get_class($user));
        });

        // Don't increment if viewer is admin or the creator
        $isBlogOwner = ((int)$blog->user_id === (int)$user->id) && ($blog->user_type === get_class($user));
        $isAdmin = $user instanceof \App\Models\Admin;

        if (!$isBlogOwner && !$isAdmin) {
            $viewCacheKey = "forum_view_{$blog->id}_user_{$user->id}";

            if (!Cache::has($viewCacheKey)) {
                $blog->increment('views_count');
                Cache::put($viewCacheKey, true, now()->addDay());
            }
        }

        return response()->json(['message' => 'success', $blog], 200);
    }

    private function rememberWithRegistry(string $key, \Closure $callback, $ttl = 600)
    {
        // Store the key in a registry list
        $registry = Cache::get('blog_cache_keys', []);
        if (!in_array($key, $registry)) {
            $registry[] = $key;
            Cache::put('blog_cache_keys', $registry, now()->addDays(7));
        }

        return Cache::remember($key, now()->addSeconds($ttl), $callback);
    }
}
