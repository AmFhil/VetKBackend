<?php

namespace App\Http\Controllers;

use App\Models\ForumChat;
use App\Traits\BroadcastsAdminEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ForumChatController extends Controller
{
    use BroadcastsAdminEvents;

    /**
     * Create Forum Post.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $validated = $request->validate([
            'title'    => 'required|string|max:255',
            'content'  => 'required|string',
            'images'   => 'nullable|array',
            'images.*' => 'sometimes|image|mimes:jpeg,png,jpg|max:5120',
            'visibility' => 'sometimes|string|in:everyone,veterinary_doctors,veterinary_clinics,veterinary_paraprofessionals,pet_owner/livestock_farmer,vendor,students'
        ]);

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $imagePaths[] = $img->store('forum_pictures', 'public');
            }
        }

        $forum = ForumChat::create([
            'user_id'   => $user->id,
            'user_type' => get_class($user),
            'title'     => $validated['title'],
            'content'   => $validated['content'],
            'images'    => $imagePaths,
            'status'    => 'archived',
            'visibility' => $validated['visibility'] ?? 'everyone'
        ]);

        try {
            $user->logActivity(
                'created',
                'Forum Created',
                "User {$user->first_name} created forum '{$forum->title}'.",
                ['forum_id' => $forum->id]
            );

            $user->sendNotification(
                'forum',
                'Forum Created',
                "Your forum post '{$forum->title}' was created.",
                ['forum_id' => $forum->id]
            );
        } catch (\Throwable $e) {
            // Optionally log silently
        }

        return response()->json([
            'message' => 'Forum chat created successfully',
            'data'    => $forum
        ], 201);
    }

    /**
     * List all published forum posts.
     */
    public function index()
    {
        $chats = Cache::remember('forum_chats_index', now()->addMinutes(10), function () {
            return ForumChat::where('status', 'published')
                ->with([
                    'user' => function ($morph) {
                        $morph->morphWith([
                            \App\Models\User::class  => ['profile', 'activeRole'],
                            \App\Models\Admin::class => [],
                        ]);
                    }
                ])
                ->latest()
                ->paginate(10);
        });

        return response()->json($chats);
    }

    /**
     * Show a single forum post by slug.
     */
    public function show($slug, Request $request)
    {
        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $cacheKey = "forum_show_{$slug}";

        $forum = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($slug) {
            return ForumChat::where('slug', $slug)
                ->where('status', 'published')
                ->with([
                    'user' => function ($morph) {
                        $morph->morphWith([
                            \App\Models\User::class  => ['profile', 'activeRole'],
                            \App\Models\Admin::class => [],
                        ]);
                    },
                    'comments' => function ($q) {
                        $q->where('flag', '!=', 'hidden')
                            ->with([
                                'user' => function ($morph) {
                                    $morph->morphWith([
                                        \App\Models\User::class  => ['profile', 'activeRole'],
                                        \App\Models\Admin::class => [],
                                    ]);
                                },
                                'replies' => function ($r) {
                                    $r->where('flag', '!=', 'hidden')->with([
                                        'user' => function ($morph) {
                                            $morph->morphWith([
                                                \App\Models\User::class  => ['profile', 'activeRole'],
                                                \App\Models\Admin::class => [],
                                            ]);
                                        }
                                    ]);
                                }
                            ]);
                    },
                    'likes'
                ])
                ->firstOrFail();
        });

        $viewCacheKey = $user
            ? "forum_view_{$forum->id}_user_{$user->id}"
            : "forum_view_{$forum->id}_ip_{$request->ip()}";

        if (!Cache::has($viewCacheKey)) {
            $forum->increment('views_count');
            Cache::put($viewCacheKey, true, now()->addDay());
            Cache::forget($cacheKey); // ✅ still needed for updated view count
        }

        return response()->json($forum);
    }

    /**
     * Update a forum post (only owner).
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $forum = ForumChat::findOrFail($id);

        if ($forum->user_id !== $user->id || get_class($user) !== $forum->user_type) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title'    => 'sometimes|string|max:255',
            'content'  => 'sometimes|string',
            'images'   => 'nullable|array',
            'images.*' => 'sometimes|image|mimes:jpeg,png,jpg|max:5120',
            'visibility' => 'sometimes|string|in:everyone,veterinary_doctors,veterinary_clinics,veterinary_paraprofessionals,pet_owner/livestock_farmer,vendor,students'
        ]);

        $imagePaths = $forum->images ?? [];

        if ($request->hasFile('images')) {
            foreach ($imagePaths as $oldImage) {
                Storage::disk('public')->delete($oldImage);
            }
            $imagePaths = [];
            foreach ($request->file('images') as $img) {
                $imagePaths[] = $img->store('forum_pictures', 'public');
            }
        }

        $forum->update([
            'title'   => $validated['title'] ?? $forum->title,
            'content' => $validated['content'] ?? $forum->content,
            'images'  => $imagePaths,
            'visibility' => $validated['visibility'] ?? $forum->visibility
        ]);

        $user->logActivity('forum_updated', "Updated forum '{$forum->title}'", null, ['forum_id' => $forum->id]);
        $user->sendNotification('forum', "Your forum '{$forum->title}' was updated.", null, ['forum_id' => $forum->id]);

        return response()->json(['message' => 'Forum chat updated successfully', 'data' => $forum]);
    }

    /**
     * Delete a forum post (only owner).
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $forum = ForumChat::findOrFail($id);

        if ($forum->user_id !== $user->id || get_class($user) !== $forum->user_type) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $forum->delete();

        $user->logActivity('forum_deleted', "Deleted forum '{$forum->title}'", null, ['forum_id' => $id]);
        $user->sendNotification('forum', "Your forum '{$forum->title}' was deleted.", null, ['forum_id' => $id]);

        return response()->json(['message' => 'Forum chat deleted successfully']);
    }

    /**
     * Trending forums (User).
     */
    public function trending()
    {
        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $trending = Cache::remember('trending_forums_chat', now()->addMinutes(10), function () {
            return ForumChat::where('status', 'published')
                ->withCount(['comments', 'likes'])
                ->with([
                    'user' => function ($morph) {
                        $morph->morphWith([
                            \App\Models\User::class  => ['profile', 'activeRole'],
                            \App\Models\Admin::class => [],
                        ]);
                    }
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
     * Get all forums for Admin.
     */
    public function getAllForumChat()
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $forums = Cache::remember('forum_chats_admin', now()->addMinutes(10), function () {
            return ForumChat::with(['user', 'comments', 'likes'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        });

        return response()->json($forums);
    }

    /**
     * Share a forum post.
     */
    public function share($id)
    {
        $forum = ForumChat::findOrFail($id);
        $forum->increment('shares_count');

        // ✅ model events don’t fire on increment, so clear manually
        Cache::forget("forum_show_{$forum->slug}");

        return response()->json(['message' => 'Share recorded']);
    }

    // get forums through visibility
    public function getForumsByVisibility($visibility){
        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $forums = ForumChat::where('status', 'published')
            ->where('visibility', $visibility)
            ->with([
                'user' => function ($morph) {
                    $morph->morphWith([
                        \App\Models\User::class  => ['profile', 'activeRole'],
                        \App\Models\Admin::class => [],
                    ]);
                }
            ])
            ->latest()
            ->paginate(10);

        return response()->json($forums, 200);
    }
}
