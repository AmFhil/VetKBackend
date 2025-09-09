<?php
namespace App\Http\Controllers;

use App\Models\BlogChat;
use App\Models\BlogChatComment;
use Illuminate\Http\Request;

class BlogChatCommentController extends Controller
{
    // Add comment
    public function addComment(Request $request, $blogId)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'comment' => 'required|string|max:500',
            'parent_id' => 'nullable|exists:blog_chat_comments,id',
        ]);

        $blog = BlogChat::findOrFail($blogId);

        $comment = BlogChatComment::create([
            'blog_chat_id' => $blog->id,
            'user_id'      => $user->id,
            'user_type'    => get_class($user),
            'comment'      => $request->comment,
            'parent_id'    => $request->parent_id,
        ]);

        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => $comment
        ], 200);
    }

    // Fetch comments with nested replies
    public function fetchComments($blogId)
    {
        $comments = BlogChatComment::where('blog_chat_id', $blogId)
            ->with(['user', 'replies.user'])
            ->whereNull('parent_id')
            ->latest()
            ->get();

        return response()->json($comments);
    }

    // Delete comment
    public function deleteComment($commentId)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment = BlogChatComment::findOrFail($commentId);

        if ($comment->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }

    // Edit comment
    public function editComment(Request $request, $commentId)
    {
        $user = auth()->user();
        if(!$user){
            return response()->json(['message' => "Unauthorized"], 403);
        }

        $comment = BlogChatComment::findOrFail($commentId);

        if ($comment->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'comment' => 'required|string|max:500',
        ]);

        $comment->update(['comment' => $request->comment]);

        return response()->json([
            'message' => 'Comment updated successfully',
            'comment' => $comment
        ], 200);
    }

    // User flag
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
        $comment->update(['flag' => $request->flag]);

        return response()->json(['message' => 'Comment flagged as ' . $request->flag]);
    }

    // Admin flag
    public function updateFlag(Request $request, $id)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'flag' => 'required|in:none,spam,abuse,hidden',
        ]);

        $comment = BlogChatComment::findOrFail($id);
        $comment->update(['flag' => $request->flag]);

        return response()->json([
            'message' => 'Comment flag updated by admin',
            'comment' => $comment
        ]);
    }
}
