<?php

namespace App\Http\Controllers;

use App\Models\BlogChat;
use App\Models\BlogChatComment;
use Illuminate\Http\Request;

class BlogChatCommentController extends Controller
{
    //
    public function addComment(Request $request, $blogId)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'comments' => 'required|string|max:500',
            'parent_id' => 'nullable|exits:blog_chat_comments,id',
        ]);

        $blog = BlogChat::findOrFail($blogId);

        $comment = BlogChatComment::create([
            'blog_chat_id' => $blog->id,
            'user_id' => $user->id,
            'user_type' => get_class($user),
            'comment' => $request->comments,
            'parent_id' => $request->parent_id
        ]);

        // incrementing comment count
        $blog->increment('comments_count');

        return response()->json(['message' => 'Comment added successfully', 'comment' => $comment], 200);
    }

    public function fetchComments($blogId)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comments = BlogChatComment::where('blog_chat_id', $blogId)
            ->with('replies')
            ->whereNull('parent_id')
            ->latest()
            ->get();

        return response()->json($comments);
    }

    public function deleteComment($commentId)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $blog = BlogChat::findOrFail($commentId);

        $blog->delete();

        return response()->json(['message' => 'Blog deleted successfully']);
    }

    public function editComment(Request $request, $commentId)
    {
        $user = auth()->user();

        if(!$user){
            return response()->json(['message' => "Unauthorized"], 403);
        }

        $comment = BlogChatComment::findOrFail($commentId);

        $request->validate([
            'comments' => 'required|string|max:500',
        ]);

        $comment->fill($request->only(['comments']));
        $comment->save();

        return response()->json(['message' => 'Comment updated successfully', 'comment' => $comment], 200);
    }

    // Flag a comment
    public function flag(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 403);
        }

        $request->validate([
            'flag' => 'required|in:spam,abuse',
        ]);

        $comment = BlogChatComment::findOrFail($id);

        // update flag
        $comment->flag = $request->flag;
        $comment->save();

        return response()->json([
            'message' => 'Comment flagged as ' . $request->flag
        ]);
    }
}
