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
            return response()->json(['message' => 'unliked']);
        } else {
            BlogChatLike::create([
                'blog_chat_id' => $blog->id,
                'user_id' => $user->id,
                'user_type' => get_class($user),
            ]);
            return response()->json(['message' => 'liked']);
        }
    }
}
