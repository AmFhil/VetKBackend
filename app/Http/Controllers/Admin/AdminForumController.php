<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminForumController extends Controller
{
    use \App\Traits\BroadcastsAdminEvents;

    // approve forum chat content
    public function approveForumChat(Request $request)
    {
        $request->validate([
            'forum_chat_id' => 'required|integer',
            'user_id' => 'required|integer',
            'user_role' => 'required|string'
        ]);

        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $content = ForumChat::where('id', $request->forum_chat_id)
            ->where('user_id', $request->user_id)
            ->first();

        if (!$content) {
            return response()->json(['message' => 'Forum chat not found'], 404);
        }

        try {
            $content->update(['status' => 'published']);

            $this->createAdminActivity(
                $admin->id,
                "Forum Chat Approved",
                "{$content->title} has been reviewed and approved by you",
                "Hi user, your content with title: {$content->title} has been reviewed and approved to be on our platform.",
                [
                    'forum_id' => $content->id,
                    'staff_id' => $admin->staff_id,
                    'role'     => $request->user_role
                ]
            );

            $user = \App\Models\User::find($request->user_id);
            if ($user) {
                $user->notify(new \App\Notifications\AdminActionNotification(
                    'Forum Chat Content Approved',
                    "Hi user, your content with title: {$content->title} has been reviewed and it has been approved to be on our platform."
                ));
            }

            $this->createAdminNotification(
                $admin->id,
                "Forum Chat Content Approved",
                'forum',
                "Hi user, your content with title: {$content->title} has been approved to be on our platform.",
                ['forum_id' => $content->id, 'staff_id' => $admin->staff_id, 'role' => $request->user_role]
            );

            return response()->json(['detail' => 'You successfully approved a Forum Chat Content']);
        } catch (\Throwable $th) {
            return response()->json(['code' => 400, 'detail' => $th->getMessage()]);
        }
    }

    // ✅ reject forum chat content
    public function rejectForumChat(Request $request)
    {
        $request->validate([
            'forum_chat_id' => 'required|integer',
            'user_id' => 'required|integer',
            'user_role' => 'required|string'
        ]);

        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $content = ForumChat::where('id', $request->forum_chat_id)
            ->where('user_id', $request->user_id)
            ->first();

        if (!$content) {
            return response()->json(['message' => 'Forum chat not found'], 404);
        }

        try {
            $content->update(['status' => 'archived']);

            $this->createAdminActivity(
                $admin->id,
                "Forum Chat Rejected",
                "{$content->title} has been reviewed and rejected by you",
                "Hi user, we are sorry to let you know that your content with title: {$content->title} has been reviewed and rejected.",
                ['forum_id' => $content->id, 'staff_id' => $admin->staff_id, 'role' => $request->user_role]
            );

            $user = \App\Models\User::find($request->user_id);
            if ($user) {
                $user->notify(new \App\Notifications\AdminActionNotification(
                    'Forum Chat Content Rejected',
                    "Hi user, your content with title: {$content->title} has been reviewed and it has been rejected."
                ));
            }

            $this->createAdminNotification(
                $admin->id,
                "Forum Chat Content Rejected",
                'forum',
                "Hi user, your content with title: {$content->title} has been rejected on our platform.",
                ['forum_id' => $content->id, 'staff_id' => $admin->staff_id, 'role' => $request->user_role]
            );


            return response()->json(['detail' => 'You successfully rejected a Forum Chat Content']);
        } catch (\Throwable $th) {
            return response()->json(['code' => 400, 'detail' => $th->getMessage()]);
        }
    }

    public function getForumApprovals()
    {
        $forums = Cache::remember('admin_forums', now()->addMinutes(10), function () {
            return ForumChat::where('status', 'published')
                ->with([
                    'user' => function ($morph) {
                        $morph->morphWith([
                            \App\Models\User::class => ['profile', 'activeRole'],
                            \App\Models\Admin::class => [],
                        ]);
                    },
                    'comments',
                    'likes'
                ])
                ->latest()
                ->paginate(10);
        });

        return response()->json($forums);
    }

    // search forum chats
    public function searchForumChats(Request $request)
    {
        $searchTerm = $request->input('query');

        $forums = ForumChat::where('title', 'LIKE', '%' . $searchTerm . '%')
            ->orWhere('content', 'LIKE', '%' . $searchTerm . '%')
            ->with([
                'user' => function ($morph) {
                    $morph->morphWith([
                        \App\Models\User::class => ['profile', 'activeRole'],
                        \App\Models\Admin::class => [],
                    ]);
                },
                'comments',
                'likes'
            ])
            ->latest()
            ->paginate(10);

        return response()->json($forums);
    }
}
