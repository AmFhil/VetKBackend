<?php

namespace App\Http\Controllers;

use App\Models\BlogChat;
use App\Models\BlogChatLike;
use Illuminate\Http\Request;

class BlogChatLikeController extends Controller
{
    //
    public function toggleLike($blogId)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $blog = BlogChat::findOrFail($blogId);

        $like = BlogChatLike::where('blog_chat_id', $blog->id)
            ->where('user_id', $user->id)
            ->first();

            if($like){
                $like->delete();
                $blog->decrement('likes_count');
                return response()->json(['message' => 'unlike']);
            }else{
                BlogChatLike::create([
                    'blog_chat_id' => $blog->id,
                    'user_id' => $user->id,
                    'user_type' => get_class($user)
                ]);
                $blog->increment('likes_count');
                return response()->json(['message' => 'liked']);
            }

    }
}
