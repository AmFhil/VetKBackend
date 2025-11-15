<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\ForumChat;
use App\Models\ForumChatLike;
use App\Models\Membership;
use App\Models\User;
use App\Traits\BroadcastsAdminEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Determine user active role
        $roleName = $user instanceof \App\Models\Admin
            ? 'admin'
            : $user->activeRole?->name;

        // Allowed visibilities based on role
        $allowedVisibilities = match ($roleName) {
            'admin' => ['everyone', 'veterinary_doctor', 'veterinary_clinic', 'veterinary_paraprofessional', 'pet_owner', 'livestock_farmer', 'vendor', 'others'],
            default => [$roleName, 'everyone'],
        };

        // Validate request with dynamic visibility rule
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg|max:5120',
            'visibility' => ['required', 'string', function ($attribute, $value, $fail) use ($allowedVisibilities) {
                if (!in_array($value, $allowedVisibilities)) {
                    $fail("You are not allowed to post with visibility '{$value}'.");
                }
            }],
        ]);

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('forum_pictures', 'public');
        }


        $forum = ForumChat::create([
            'user_id' => $user->id,
            'user_type' => get_class($user),
            'title' => $validated['title'],
            'content' => $validated['content'],
            'image' => $imagePath,
            'status' => 'archived',
            'visibility' => $validated['visibility'] ?? 'everyone',
        ]);

        Membership::firstOrCreate([
            'member_id'   => $user->id,
            'member_type' => get_class($user),
            'joinable_id' => $forum->id,
            'joinable_type' => ForumChat::class,
        ]);


        try {
            if ($user instanceof \App\Models\Admin) {
                $this->createAdminActivity(
                    $user->id,
                    'created',
                    'Forum Created',
                    "Admin {$user->first_name} created forum '{$forum->title}'.",
                    ['forum_id' => $forum->id]
                );

                $this->createAdminNotification(
                    $user->id,
                    'forum',
                    'Forum Created',
                    "Your forum post '{$forum->title}' was created.",
                    ['forum_id' => $forum->id]
                );
            } else if ($user instanceof User) {
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
            }
        } catch (\Throwable $e) {
            // Log silently
        }

        return response()->json([
            'message' => 'Forum chat created successfully',
            'data' => $forum
        ], 201);
    }


    /**
     * List all published forum posts.
     */
    public function index()
    {
        $user = auth('user')->user() ?: auth('admin')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $roleName = $user instanceof \App\Models\Admin
            ? 'admin'
            : $user->activeRole?->name;

        $visibilityFilter = $roleName === 'admin'
            ? [] // Admin sees all
            : ['everyone', $roleName];

        // Cache key now per-role only (no user ID)
        $cacheKey = "forum_chats_index_{$roleName}";
        Log::info("Fetching forum chats with cache key: {$cacheKey}");

        // Cache the base paginator (without user-specific transformations)
        $paginator = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($visibilityFilter) {
            $query = ForumChat::where('status', 'published')
                ->with(['likers']);

            if (!empty($visibilityFilter)) {
                $query->whereIn('visibility', $visibilityFilter);
            }

            return $query->latest()->paginate(10);
        });

        // Apply user-specific transformations after caching
        $chats = $paginator->through(function ($chat) use ($user) {
            $chat->has_liked = $chat->likers->contains(function ($like) use ($user) {
                return ((int) $like->user_id === (int) $user->id) && ($like->user_type === get_class($user));
            });
            return $chat;
        });

        return response()->json(['chats' => $chats], 200);
    }

    /**
     * Show a single forum post by slug.
     */
    public function show($slug, Request $request)
    {
        $user = auth('user')->user() ?: auth('admin')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $roleName = $user instanceof \App\Models\Admin
            ? 'admin'
            : $user->activeRole?->name;

        $visibilityFilter = $roleName === 'admin'
            ? [] // Admin sees all
            : ['everyone', $roleName];

        $cacheKey = "forum_show_{$slug}";

        $forum = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($slug, $visibilityFilter) {
            $query = ForumChat::where('slug', $slug)
                ->where('status', 'published')
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
                    'likers',
                ]);

            if (!empty($visibilityFilter)) {
                $query->whereIn('visibility', $visibilityFilter);
            }

            return $query->firstOrFail();
        });

        // Use eager-loaded likers to avoid extra query
        $forum->has_liked = $forum->likers->contains(function ($like) use ($user) {
            return ((int) $like->user_id === (int) $user->id) && ($like->user_type === get_class($user));
        });

        // Don't increment if viewer is admin or the creator
        $isForumOwner = ((int)$forum->user_id === (int)$user->id) && ($forum->user_type === get_class($user));
        $isAdmin = $user instanceof \App\Models\Admin;

        if (!$isForumOwner && !$isAdmin) {
            $viewCacheKey = "forum_view_{$forum->id}_user_{$user->id}";

            if (!Cache::has($viewCacheKey)) {
                $forum->increment('views_count');
                Cache::put($viewCacheKey, true, now()->addDay());
            }
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

        if (((int) $forum->user_id !== (int) $user->id) || (get_class($user) !== $forum->user_type)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // check if forum is approved
        if ($forum->status === 'published') {
            return response()->json(['message' => 'Cannot edit an approved forum'], 401);
        }

        // Determine user active role
        $roleName = $user instanceof \App\Models\Admin
            ? 'admin'
            : $user->activeRole?->name;

        // Allowed visibilities based on role
        $allowedVisibilities = match ($roleName) {
            'admin' => ['everyone', 'veterinary_doctor', 'veterinary_clinic', 'veterinary_paraprofessional', 'pet_owner', 'livestock_farmer', 'vendor', 'others'],
            default => [$roleName, 'everyone'],
        };

        $validated = $request->validate([
            'title'    => 'sometimes|string|max:255',
            'content'  => 'sometimes|string',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg|max:5120',
            'visibility' => ['sometimes', 'string', function ($attribute, $value, $fail) use ($allowedVisibilities) {
                if (!in_array($value, $allowedVisibilities)) {
                    $fail("You are not allowed to post with visibility '{$value}'.");
                }
            }],
        ]);

        $imagePaths = $forum->images ?? null;

        if ($request->hasFile('image')) {
            if ($forum->image && Storage::disk('public')->exists($forum->image)) {
                Storage::disk('public')->delete($forum->image);
            }
            $imagePaths = $request->file('image')->store('forum_pictures', 'public');
        }

        $forum->update([
            'title'   => $validated['title'] ?? $forum->title,
            'content' => $validated['content'] ?? $forum->content,
            'image'  => $imagePaths,
            'visibility' => $validated['visibility'] ?? $forum->visibility
        ]);

        if ($user instanceof Admin) {
            $this->createAdminActivity(
                $user->id,
                'forum_updated',
                "Updated forum '{$forum->title}'",
                null,
                ['forum_id' => $forum->id]
            );
        } elseif ($user instanceof User) {
            $user->logActivity(
                'forum_updated',
                "Updated forum '{$forum->title}'",
                null,
                ['forum_id' => $forum->id]
            );
        }

        return response()->json(['message' => 'Forum chat updated successfully', 'data' => $forum]);
    }

    /**
     * Delete a forum post (only owner).
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $forum = ForumChat::findOrFail($id);

        if (((int) $forum->user_id !== (int) $user->id) || (get_class($user) !== $forum->user_type)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $forumTitle = $forum->title;
        $forumId = $forum->id;

        $forum->delete();

        if ($user instanceof Admin) {
            $this->createAdminActivity(
                $user->id,
                'forum_deleted',
                "Deleted forum '{$forumTitle}'",
                null,
                ['forum_id' => $forumId]
            );
        } elseif ($user instanceof User) {
            $user->logActivity(
                'forum_deleted',
                "Deleted forum '{$forumTitle}'",
                null,
                ['forum_id' => $forumId]
            );
        }

        return response()->json(['message' => 'Forum chat deleted successfully']);
    }

    /**
     * Trending forums (User).
     */
    public function trending()
    {
        $user = auth('user')->user() ?: auth('admin')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $roleName = $user instanceof \App\Models\Admin
            ? 'admin'
            : $user->activeRole?->name;

        $visibilityFilter = $roleName === 'admin'
            ? [] // Admin sees all
            : ['everyone', $roleName];

        $cacheKey = "trending_forums_chat_{$roleName}";

        $trending = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($visibilityFilter) {
            $query = ForumChat::where('status', 'published')
                ->withCount(['comments', 'likes'])
                ->with(['likers'])
                ->selectRaw('
                (views_count * 0.3) +
                (likes_count * 0.5) +
                (comments_count * 0.7) +
                (shares_count * 1) as score
            ');

            if (!empty($visibilityFilter)) {
                $query->whereIn('visibility', $visibilityFilter);
            }

            return $query->orderByDesc('score')->take(10)->get();
        });

        $forum = $trending->map(function ($chat) use ($user) {
            $chat->has_liked = $chat->likers->contains(function ($like) use ($user) {
                return ((int) $like->user_id === (int) $user->id) && ($like->user_type === get_class($user));
            });
            return $chat;
        });

        return response()->json($forum, 200);
    }

    /**
     * Get all forums for Admin.
     */
    public function getAllForumChat()
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $forums = Cache::remember('forum_chats_admin', now()->addMinutes(10), function () use ($admin) {
            return ForumChat::with([
                'likers'
            ])
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        });

        $AdminChat = $forums->through(function ($chat) use ($admin) {
            $chat->has_liked = $chat->likers->contains(function ($like) use ($admin) {
                return ((int) $like->user_id === (int) $admin->id) && ($like->user_type === get_class($admin));
            });

            return $chat;
        });

        return response()->json($AdminChat, 200);
    }

    /**
     * Share a forum post.
     */
    public function share($id)
    {
        $user = auth('user')->user() ?: auth('admin')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $roleName = $user instanceof \App\Models\Admin
            ? 'admin'
            : $user->activeRole?->name;

        $forum = ForumChat::findOrFail($id);
        $forum->increment('shares_count');

        // model events don’t fire on increment, so clear manually
        Cache::forget("forum_show_{$forum->slug}");
        Cache::forget('forum_chats_admin');
        Cache::forget('admin_forums');

        if ($forum?->id) {
            Cache::forget("forum_likers_{$forum->id}");
            Cache::forget("forum_chat_comment_{$forum->id}");
        }

        if ($forum?->user_id && $forum?->user_type) {
            $classBase = class_basename($forum->user_type);

            // Forget per-user cache variants
            Cache::forget("user_forum_posts_{$classBase}_{$forum->user_id}");
        }
        Cache::forget("forum_visibility_{$forum->visibility}");
        Cache::forget("forum_chats_index_{$roleName}");
        Cache::forget("trending_forums_chat_{$roleName}");


        return response()->json(['message' => 'Share recorded']);
    }

    // get forums through visibility
    public function getForumsByVisibility($visibility)
    {
        $user = auth('user')->user() ?: auth('admin')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $roleName = $user instanceof \App\Models\Admin
            ? 'admin'
            : $user->activeRole?->name;

        // Only admins can fetch any visibility
        if ($roleName !== 'admin' && !in_array($visibility, [$roleName, 'everyone'])) {
            return response()->json(['message' => "You are not allowed to view '{$visibility}' forums."], 403);
        }

        $cacheKey = "forum_visibility_{$visibility}";

        $paginate = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($visibility, $user) {
            $query = ForumChat::where('status', 'published')
                ->where('visibility', $visibility)
                ->with(['likers']);

            return $query->latest()->paginate(10);
        });

        $forums = $paginate->through(function ($chat) use ($user) {
            $chat->has_liked = $chat->likers->contains(function ($like) use ($user) {
                return ((int) $like->user_id === (int) $user->id) && ($like->user_type === get_class($user));
            });
            return $chat;
        });

        return response()->json($forums, 200);
    }

    // get all users post
    public function getUserPosts()
    {
        $user = auth('user')->user() ?: auth('admin')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $cacheKey = "user_forum_posts_" . class_basename(get_class($user)) . "_{$user->id}";

        $query = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($user) {
            return ForumChat::where('user_id', $user->id)
                ->where('user_type', get_class($user))
                ->with(['likers'])
                ->latest()
                ->paginate(10);
        });

        $forums = $query->through(function ($chat) use ($user) {
                $chat->has_liked = $chat->likers->contains(function ($like) use ($user) {
                    return ((int) $like->user_id === (int) $user->id) && ($like->user_type === get_class($user));
                });
                return $chat;
            });

        return response()->json($forums, 200);
    }

    public function getLikers($id)
    {
        $user = auth('user')->user() ?: auth('admin')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $likers = Cache::remember("forum_likers_{$id}", now()->addMinutes(10), function () use ($id) {
            return ForumChatLike::where('forum_chat_id', $id)
                ->with(['user' => function ($morph) {
                    $morph->morphWith([
                        \App\Models\User::class => ['profile', 'activeRole'],
                        \App\Models\Admin::class => [],
                    ]);
                }])
                ->get()
                ->map(function ($like) {
                    $user = $like->user;
                    if ($user instanceof \App\Models\User) {
                        return [
                            'id' => $user->id,
                            'user_type' => 'User',
                            'name' => $user->first_name . ' ' . $user->last_name,
                        ];
                    } elseif ($user instanceof \App\Models\Admin) {
                        return [
                            'id' => $user->id,
                            'user_type' => 'Admin',
                            'name' => $user->first_name . ' ' . $user->last_name,
                        ];
                    }
                    return null;
                })
                ->filter() // Remove null entries
                ->values();
        });

        if ($likers->isEmpty()) {
            return response()->json(['message' => 'No users have liked this forum post'], 200);
        }

        return response()->json(['likers' => $likers], 200);
    }
}
