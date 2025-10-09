<?php

namespace App\Http\Controllers;

use App\Events\BlogLiked;
use App\Models\Admin;
use App\Models\BlogChat;
use App\Models\BlogChatLike;
use App\Models\User;
use App\Traits\BroadcastsAdminEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlogChatLikeController extends Controller
{
    use BroadcastsAdminEvents;

    public function toggleLike($blogId)
    {
        $user = auth('user')->user() ?: auth('admin')->user();

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

            event(new BlogLiked($user, $blog->id));

            // LOG ACTIVITY
            if ($user instanceof Admin) {
                $this->createAdminActivity(
                    $user->id,
                    'blog_unlike',
                    "UnLiked Blog Chat",
                    "You unLiked the blog chat titled '{$blog->title}'.",
                    ['blog_id' => $blog->id, 'action' => 'unlike']
                );
            } elseif ($user instanceof User) {
                $user->logActivity(
                    'blog_unlike',
                    "UnLiked Blog Chat",
                    "You unLiked the blog chat titled '{$blog->title}'.",
                    ['blog_id' => $blog->id, 'action' => 'unlike']
                );
            }

            return response()->json(['message' => 'unliked']);
        } else {
            BlogChatLike::create([
                'blog_chat_id' => $blog->id,
                'user_id' => $user->id,
                'user_type' => get_class($user),
            ]);

            event(new BlogLiked($user, $blog->id));

            // LOG ACTIVITY
            if ($user instanceof Admin) {
                $this->createAdminActivity(
                    $user->id,
                    'blog_like',
                    "Liked Blog Chat",
                    "You liked the blog chat titled '{$blog->title}'.",
                    ['blog_id' => $blog->id, 'action' => 'like']
                );
            } elseif ($user instanceof User) {
                $user->logActivity(
                    'blog_like',
                    "Liked Blog Chat",
                    "You liked the blog chat titled '{$blog->title}'.",
                    ['blog_id' => $blog->id, 'action' => 'like']
                );
            }


            // send notification to blog author if not the same user
            if ($blog->user_id !== $user->id || get_class($blog->user) !== get_class($user)) {
                $blogUser = $blog->user;
                if ($blogUser instanceof Admin) {
                    $this->createAdminNotification(
                        $blogUser->id,
                        'blog_liked_by_other',
                        'Your Blog Chat was Liked',
                        "<strong>{$user->first_name} {$user->last_name}</strong> liked your blog chat titled <strong>{$blog->title}</strong>.",
                        ['blog_id' => $blog->id, 'liker_id' => $user->id]
                    );
                }
                if ($blogUser instanceof User) {
                    $blog->user->sendNotification(
                        'blog_liked_by_other',
                        'Your Blog Chat was Liked',
                        "<strong>{$user->first_name} {$user->last_name}</strong> liked your blog chat titled <strong>{$blog->title}</strong>.",
                        ['blog_id' => $blog->id, 'liker_id' => $user->id]
                    );
                }
            }

            return response()->json(['message' => 'liked']);
        }
    }
}
