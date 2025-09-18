<?php

namespace App\Http\Controllers;

use App\Models\ForumChat;
use App\Models\ForumChatLike;
use Illuminate\Http\Request;

class ForumChatLikeController extends Controller
{
    //
    public function toggleLike($forumId)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $forum = ForumChat::findOrFail($forumId);

        $like = ForumChatLike::where('forum_chat_id', $forum->id)
            ->where('user_id', $user->id)
            ->where('user_type', get_class($user))
            ->first();

        if ($like) {
            $like->delete();

            // LOG ACTIVITY
            $user->logActivity(
                'forum_unlike',
                "UnLiked Forum Chat",
                "You unLiked the forum chat titled '{$forum->title}'.",
                ['forum_id' => $forum->id, 'action' => 'unlike']
            );

            // SEND NOTIFICATION
            $user->sendNotification(
                'forum_unliked',
                'Forum Chat Unliked',
                "You have removed your like from <strong>{$forum->title}</strong>.",
                ['forum_id' => $forum->id]
            );

            return response()->json(['message' => 'unliked']);
        } else {
            ForumChatLike::create([
                'forum_chat_id' => $forum->id,
                'user_id' => $user->id,
                'user_type' => get_class($user),
            ]);

            // LOG ACTIVITY
            $user->logActivity(
                'forum_like',
                "Liked Forum Chat",
                "You liked the forum chat titled '{$forum->title}'.",
                ['forum_id' => $forum->id, 'action' => 'like']
            );

            // SEND NOTIFICATION
            $user->sendNotification(
                'forum_liked',
                'Forum Chat Liked',
                "You liked <strong>{$forum->title}</strong>. Your appreciation has been noted!",
                ['forum_id' => $forum->id]
            );

            return response()->json(['message' => 'liked']);
        }
    }
}
