<?php

namespace App\Http\Controllers;

use App\Models\CaseComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CaseCommentController extends Controller
{
    //
    public function addComment(Request $request)
    {
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'unauthorized'], 403);
        }

        $request->validate([
            'case_id' => 'required|exists:cases,id',
            'comment' => 'required|string',
            'parent_id' => 'nullable|exists:case_comment,id',
        ]);

        $comment = CaseComment::create([
            'case_id' => $request->case_id,
            'user_id' => $user->id,
            'parent_id' => $request->parent_id,
            'comment' => $request->comment
        ]);

        cache::forget("case_comments_{$request->case_id}");

        return response()->json(['message' => 'Comment added successfully', 'comment' => $comment], 200);
    }

    public function getComments($caseId)
    {
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'unauthorized'], 403);
        }

        $comments = Cache::remember("case_comments_{$caseId}", 60, function() use ($caseId) {
            return CaseComment::where('case_id', $caseId)
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->get();
        });
        
        return response()->json(['comments' => $comments], 200);
    }

    public function deleteComment($id)
    {
        $admin = auth('user')->user();
        if (!$admin) {
            return response()->json(['message' => 'unauthorized'], 403);
        }

        $comment = CaseComment::find($id);
        if (!$comment) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        $caseId = $comment->case_id;
        $comment->delete();

        cache::forget("case_comments_{$caseId}");

        return response()->json(['message' => 'Comment deleted successfully'], 200);
    }

    public function updateComment(Request $request, $id)
    {
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'unauthorized'], 403);
        }

        $comment = CaseComment::find($id);
        if (!$comment) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        if ($comment->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'comment' => 'required|string',
        ]);

        $comment->comment = $request->comment;
        $comment->save();

        cache::forget("case_comments_{$comment->case_id}");

        return response()->json(['message' => 'Comment updated successfully', 'comment' => $comment], 200);
    }
}
