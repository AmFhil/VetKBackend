<?php

namespace App\Http\Controllers;

use App\Models\BlogChat;
use App\Models\BlogChatComment;
use App\Models\Admin;
use App\Models\User;
use App\Traits\BroadcastsAdminEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlogChatCommentController extends Controller
{
    use BroadcastsAdminEvents;

    // Add comment
    public function addComment(Request $request, $blogId)
    {
        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $request->validate([
            'comment'   => 'required|string|max:500',
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

        // Log + Notify (user)
        $user->logActivity(
            'blog_comment_add',
            'Added a comment',
            "You commented on '{$blog->title}': \"{$comment->comment}\"",
            ['blog_id' => $blog->id, 'comment_id' => $comment->id]
        );

        $user->sendNotification(
            'blog_comment_added',
            'Comment added',
            "Your comment on <strong>{$blog->title}</strong> was posted successfully.",
            ['blog_id' => $blog->id, 'comment_id' => $comment->id]
        );

        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => $comment
        ], 200);
    }

    // Fetch comments with nested replies
    public function fetchComments($blogId)
    {
        $comments = BlogChatComment::where('blog_chat_id', $blogId)
            ->with([
                'user:id,first_name,last_name,profile_picture',
                'user.profile:id,user_id,profile_image',
                'replies.user:id,first_name,last_name,profile_picture',
                'replies.user.profile:id,user_id,profile_image',
            ])
            ->whereNull('parent_id')
            ->latest()
            ->get();

        return response()->json($comments);
    }

    // Delete comment
    public function deleteComment($commentId)
    {
        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $comment = BlogChatComment::findOrFail($commentId);

        if ($comment->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $commentContent = $comment->comment;
        $comment->delete();

        // Log + Notify (user)
        $user->logActivity(
            'blog_comment_delete',
            'Deleted a comment',
            "You deleted your comment: \"{$commentContent}\"",
            ['comment_id' => $commentId]
        );

        $user->sendNotification(
            'blog_comment_deleted',
            'Comment deleted',
            "Your comment <em>\"{$commentContent}\"</em> has been removed.",
            ['comment_id' => $commentId]
        );

        return response()->json(['message' => 'Comment deleted successfully']);
    }

    // Edit comment
    public function editComment(Request $request, $commentId)
    {
        $user = auth()->user();
        if (!$user) return response()->json(['message' => "Unauthorized"], 403);

        $comment = BlogChatComment::findOrFail($commentId);
        if ($comment->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'comment' => 'required|string|max:500',
        ]);

        $oldComment = $comment->comment;
        $comment->update(['comment' => $request->comment]);

        // Log + Notify (user)
        $user->logActivity(
            'blog_comment_edit',
            'Edited a comment',
            "You updated your comment from \"{$oldComment}\" to \"{$comment->comment}\"",
            ['comment_id' => $commentId]
        );

        $user->sendNotification(
            'blog_comment_updated',
            'Comment updated',
            "Your comment has been updated to: <em>\"{$comment->comment}\"</em>.",
            ['comment_id' => $commentId]
        );

        return response()->json([
            'message' => 'Comment updated successfully',
            'comment' => $comment
        ], 200);
    }

    // User flag
    // User flag - with rate limiting
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

        // Rate limiting: prevent same user from flagging same comment repeatedly
        $key = "user:{$user->id}:comment:{$comment->id}:flag";
        if (Cache::has($key)) {
            return response()->json([
                'message' => 'You are flagging too frequently. Please wait before retrying.'
            ], 429);
        }
        Cache::put($key, true, now()->addMinutes(2)); // lock for 2 minutes

        $oldFlag = $comment->flag;
        $comment->update(['flag' => $request->flag]);

        // Log + notify user (so they have record of their own action)
        $user->logActivity(
            'comment_flagged',
            'You flagged a comment',
            "You flagged comment #{$comment->id} (blog #{$comment->blog_chat_id}) as '{$request->flag}'. Previous flag: '{$oldFlag}'.",
            [
                'comment_id' => $comment->id,
                'blog_id'    => $comment->blog_chat_id,
                'flag'       => $request->flag,
            ]
        );

        $user->sendNotification(
            'comment_flag',
            'Comment flagged',
            "You flagged a comment as <strong>{$request->flag}</strong>. Our moderators may review this action.",
            [
                'comment_id' => $comment->id,
                'blog_id'    => $comment->blog_chat_id,
                'flag'       => $request->flag,
            ]
        );

        return response()->json(['message' => 'Comment flagged as ' . $request->flag]);
    }


    // Admin flag - admin action must create admin activities/notifications
    // Admin flag - with rate limiting
    public function updateFlag(Request $request, $id)
    {
        $actor = auth('admin')->user();
        if (!$actor) return response()->json(['message' => 'Unauthorized'], 403);

        $request->validate([
            'flag' => 'required|in:none,spam,abuse,hidden',
        ]);

        $comment = BlogChatComment::findOrFail($id);

        // Rate limiting: prevent same admin from flagging same comment too often
        $key = "admin:{$actor->id}:comment:{$comment->id}:flag";
        if (Cache::has($key)) {
            return response()->json([
                'message' => 'You are performing this action too frequently. Please wait before retrying.'
            ], 429);
        }
        Cache::put($key, true, now()->addMinute()); // lock for 1 minute

        $oldFlag = $comment->flag;
        $comment->update(['flag' => $request->flag]);

        // Create admin activities + notifications for all admins
        $admins = \App\Models\Admin::all();
        foreach ($admins as $admin) {
            $this->createAdminActivity(
                $admin->id,
                'comment_flag_updated',
                'Comment flag updated',
                "Admin {$actor->first_name} {$actor->last_name} set flag '{$request->flag}' on comment #{$comment->id} (blog #{$comment->blog_chat_id}), previous flag: '{$oldFlag}'.",
                [
                    'comment_id' => $comment->id,
                    'blog_id'    => $comment->blog_chat_id,
                    'actor_id'   => $actor->id,
                    'flag'       => $request->flag
                ]
            );

            $this->createAdminNotification(
                $admin->id,
                'Comment Flag Updated',
                'comment_flag',
                "Comment #{$comment->id} was marked as '{$request->flag}' by {$actor->first_name} {$actor->last_name}.",
                [
                    'comment_id' => $comment->id,
                    'blog_id'    => $comment->blog_chat_id,
                    'flag'       => $request->flag
                ]
            );
        }

        // Notify the comment owner (if user)
        if ($comment->user_type === \App\Models\User::class) {
            $owner = \App\Models\User::find($comment->user_id);
            if ($owner) {
                $owner->logActivity(
                    'comment_flagged_by_admin',
                    'Comment flagged by admin',
                    "Admin {$actor->first_name} {$actor->last_name} updated your comment #{$comment->id} to '{$request->flag}'.",
                    ['comment_id' => $comment->id, 'flag' => $request->flag]
                );

                $owner->sendNotification(
                    'comment_flagged_admin',
                    'Your comment was flagged',
                    "An administrator marked your comment as <strong>{$request->flag}</strong>. Contact support if this is a mistake.",
                    ['comment_id' => $comment->id, 'flag' => $request->flag, 'admin_id' => $actor->id]
                );
            }
        }

        return response()->json([
            'message' => 'Comment flag updated by admin',
            'comment' => $comment
        ], 200);
    }
}
