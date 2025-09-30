<?php

namespace App\Http\Controllers;

use App\Events\ForumLiked;
use App\Models\ForumChat;
use App\Models\ForumChatLike;
use Illuminate\Http\Request;

class ForumChatLikeController extends Controller
{
    /**
     * Toggle like/unlike
     */
    public function toggleLike($forumId)
    {
        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $forum = ForumChat::findOrFail($forumId);

        $like = ForumChatLike::where('forum_chat_id', $forum->id)
            ->where('user_id', $user->id)
            ->where('user_type', get_class($user))
            ->first();

        if ($like) {
            // Unlike (no notification sent, only activity logged)
            $like->delete();

            event(new ForumLiked($forum->id, $user));

            $user->logActivity(
                'forum_unlike',
                'Forum Unliked',
                "You removed your like from the forum '{$forum->title}'.",
                ['forum_id' => $forum->id, 'action' => 'unlike']
            );

            return response()->json(['message' => 'unliked']);
        } else {
            // Like
            ForumChatLike::create([
                'forum_chat_id' => $forum->id,
                'user_id'       => $user->id,
                'user_type'     => get_class($user),
            ]);

            event(new ForumLiked($forum->id, $user));

            $user->logActivity(
                'forum_like',
                'Forum Liked',
                "You liked the forum '{$forum->title}'.",
                ['forum_id' => $forum->id, 'action' => 'like']
            );

            // Notify the forum owner if not the liker
            if ($forum->user_id !== $user->id) {
                $forum->user->sendNotification(
                    'forum_liked',
                    'Your Forum Was Liked',
                    "{$user->first_name} {$user->last_name} liked your forum <strong>{$forum->title}</strong>.",
                    ['forum_id' => $forum->id, 'from_user' => $user->id]
                );
            }

            return response()->json(['message' => 'liked']);
        }
    }
}
