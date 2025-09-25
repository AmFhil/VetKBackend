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

    // Clear blog caches (index, trending, show)
    private function clearBlogCaches($blogId): void
    {
        Cache::forget("blog_show_{$blogId}");
        Cache::forget("trending_blogs");

        // Match the same pagination as BlogChatController@index
        $perPage = 10;
        $totalBlogs = BlogChat::count();
        $lastPage = ceil($totalBlogs / $perPage);

        for ($page = 1; $page <= $lastPage; $page++) {
            Cache::forget("blogs_index_page_{$page}");
        }
    }

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

        // notify blog author if not the same user
        if ($blog->user_id !== $user->id || get_class($blog->user) !== get_class($user)) {
            $blog->user->sendNotification(
                'blog_commented_by_other',
                'New Comment on Your Blog Chat',
                "<strong>{$user->first_name} {$user->last_name}</strong> commented on your blog chat titled <strong>{$blog->title}</strong>: \"{$comment->comment}\"",
                ['blog_id' => $blog->id, 'commenter_id' => $user->id, 'comment_id' => $comment->id]
            );
        }

        // notify admin authors
        $admins = Admin::all();
        foreach ($admins as $admin) {
            $this->createAdminNotification(
                $admin->id,
                'New Blog Comment',
                'blog_comment',
                "Blog chat #{$blog->id} titled '{$blog->title}' received a new comment from {$user->first_name} {$user->last_name}.",
                ['blog_id' => $blog->id, 'comment_id' => $comment->id]
            );
        }




        $this->clearBlogCaches($blog->id);

        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => $comment
        ], 200);
    }

    // Fetch comments with nested replies
    public function fetchComments($blogId)
    {
        $comments = BlogChatComment::where('blog_chat_id', $blogId)
            ->whereNull('parent_id')
            ->where('flag', '!=', 'hidden')
            ->with([
                'user.roles:id,name',
                'user.activeRole:id,name',
                'user.profile:id,user_id,profile_image',
                'replies' => function ($q) {
                    $q->where('flag', '!=', 'hidden')
                        ->with([
                            'user.roles:id,name',
                            'user.activeRole:id,name',
                            'user.profile:id,user_id,profile_image',
                        ]);
                }
            ])
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

        $blogId = $comment->blog_chat_id;
        $commentContent = $comment->comment;
        $comment->delete();

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

        $this->clearBlogCaches($blogId);

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

        $this->clearBlogCaches($comment->blog_chat_id);

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

        $key = "user:{$user->id}:comment:{$comment->id}:flag";
        if (Cache::has($key)) {
            return response()->json([
                'message' => 'You are flagging too frequently. Please wait before retrying.'
            ], 429);
        }
        Cache::put($key, true, now()->addMinutes(2));

        $oldFlag = $comment->flag;
        $comment->update(['flag' => $request->flag]);

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

        // notify all admins
        $admins = Admin::all();
        foreach ($admins as $admin) {
            
            $this->createAdminNotification(
                $admin->id,
                'Comment Flagged',
                'comment_flag',
                "Comment #{$comment->id} was flagged as '{$request->flag}' by {$user->first_name} {$user->last_name}.",
                [
                    'comment_id' => $comment->id,
                    'blog_id'    => $comment->blog_chat_id,
                    'flag'       => $request->flag
                ]
            );
        }

        $this->clearBlogCaches($comment->blog_chat_id);

        return response()->json(['message' => 'Comment flagged as ' . $request->flag]);
    }

    // Admin flag
    public function updateFlag(Request $request, $id)
    {
        $actor = auth('admin')->user();
        if (!$actor) return response()->json(['message' => 'Unauthorized'], 403);

        $request->validate([
            'flag' => 'required|in:none,spam,abuse,hidden',
        ]);

        $comment = BlogChatComment::findOrFail($id);

        $key = "admin:{$actor->id}:comment:{$comment->id}:flag";
        if (Cache::has($key)) {
            return response()->json([
                'message' => 'You are performing this action too frequently. Please wait before retrying.'
            ], 429);
        }
        Cache::put($key, true, now()->addMinute());

        $oldFlag = $comment->flag;
        $comment->update(['flag' => $request->flag]);

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

        $this->clearBlogCaches($comment->blog_chat_id);

        return response()->json([
            'message' => 'Comment flag updated by admin',
            'comment' => $comment
        ], 200);
    }
}
