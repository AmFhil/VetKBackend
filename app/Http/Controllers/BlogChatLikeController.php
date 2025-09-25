<?php

namespace App\Http\Controllers;

use App\Models\BlogChat;
use App\Models\BlogChatLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlogChatLikeController extends Controller
{
    // Dynamically clear blog caches
    private function clearBlogCaches($blogId): void
    {
        Cache::forget("blog_show_{$blogId}");
        Cache::forget("trending_blogs");

        // Determine how many pages exist
        $perPage = 10; // must match your BlogChatController pagination
        $totalBlogs = BlogChat::count();
        $lastPage = ceil($totalBlogs / $perPage);

        for ($page = 1; $page <= $lastPage; $page++) {
            Cache::forget("blogs_index_page_{$page}");
        }
    }

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

            $this->clearBlogCaches($blog->id);

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

            // send notification to blog author if not the same user
            if ($blog->user_id !== $user->id || get_class($blog->user) !== get_class($user)) {
                $blog->user->sendNotification(
                    'blog_liked_by_other',
                    'Your Blog Chat was Liked',
                    "<strong>{$user->first_name} {$user->last_name}</strong> liked your blog chat titled <strong>{$blog->title}</strong>.",
                    ['blog_id' => $blog->id, 'liker_id' => $user->id]
                );
            }

            $this->clearBlogCaches($blog->id);

            return response()->json(['message' => 'liked']);
        }
    }
}
