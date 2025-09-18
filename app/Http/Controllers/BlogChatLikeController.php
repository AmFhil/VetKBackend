<?php

namespace App\Http\Controllers;

use App\Models\BlogChat;
use App\Models\BlogChatLike;
use Illuminate\Http\Request;

class BlogChatLikeController extends Controller
{
    public function toggleLike($blogId)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $blog = BlogChat::findOrFail($blogId);

        $like = BlogChatLike::where('blog_chat_id', $blog->id)
            ->where('user_id', $user->id)
            ->where('user_type', get_class($user))
            ->first();

        if ($like) {
            $like->delete();

            // LOG ACTIVITY
            $user->logActivity(
                'blog_unlike',
                "UnLiked Blog Chat",
                "You unLiked the blog chat titled '{$blog->title}'.",
                ['blog_id' => $blog->id, 'action' => 'unlike']
            );

            // SEND NOTIFICATION
            $user->sendNotification(
                'blog_unliked',
                'Blog Chat Unliked',
                "You have removed your like from <strong>{$blog->title}</strong>.",
                ['blog_id' => $blog->id]
            );

            return response()->json(['message' => 'unliked']);
        } else {
            BlogChatLike::create([
                'blog_chat_id' => $blog->id,
                'user_id' => $user->id,
                'user_type' => get_class($user),
            ]);

            // LOG ACTIVITY
            $user->logActivity(
                'blog_like',
                "Liked Blog Chat",
                "You liked the blog chat titled '{$blog->title}'.",
                ['blog_id' => $blog->id, 'action' => 'like']
            );

            // SEND NOTIFICATION
            $user->sendNotification(
                'blog_liked',
                'Blog Chat Liked',
                "You liked <strong>{$blog->title}</strong>. Your appreciation has been noted!",
                ['blog_id' => $blog->id]
            );

            return response()->json(['message' => 'liked']);
        }
    }
}
