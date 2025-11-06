<?php

namespace App\Http\Controllers;

use App\Events\ForumCommentAdded;
use App\Events\ForumCommentDeleted;
use App\Events\ForumCommentUpdated;
use App\Models\ForumChat;
use App\Models\ForumChatComment;
use App\Models\Admin;
use App\Models\Membership;
use App\Models\User;
use App\Traits\BroadcastsAdminEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ForumChatCommentController extends Controller
{
    use BroadcastsAdminEvents;

    /**
     * Add comment
     */
    public function addComment(Request $request, $forumId)
    {
        $user = auth('user')->user() ?: auth('admin')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $request->validate([
            'comment'   => 'required|string|max:500',
            'parent_id' => 'nullable|exists:forum_chat_comments,id',
        ]);

        $forum = ForumChat::findOrFail($forumId);

        $comment = $forum->comments()->create([
            'user_id'   => $user->id,
            'user_type' => get_class($user),
            'comment'   => $request->comment,
            'parent_id' => $request->parent_id,
        ]);

        Membership::firstOrCreate([
            'member_id'   => $user->id,
            'member_type' => get_class($user),
            'joinable_id' => $forum->id,
            'joinable_type' => ForumChat::class,
        ]);

        event(new ForumCommentAdded($comment));

        // Log activity for commenter
        if ($user instanceof Admin) {
            $this->createAdminActivity(
                $user->id,
                'forum_comment_add',
                'Comment Added',
                "You commented on forum '{$forum->title}': \"{$comment->comment}\"",
                ['forum_id' => $forum->id, 'comment_id' => $comment->id]
            );

            $this->createAdminNotification(
                $user->id,
                'forum',
                'Forum Created',
                "Your forum post '{$forum->title}' was created.",
                ['forum_id' => $forum->id]
            );
        } elseif ($user instanceof User) {
            $user->logActivity(
                'forum_comment_add',
                'Comment Added',
                "You commented on forum '{$forum->title}': \"{$comment->comment}\"",
                ['forum_id' => $forum->id, 'comment_id' => $comment->id]
            );

            $user->sendNotification(
                'forum',
                'Forum Created',
                "Your forum post '{$forum->title}' was created.",
                ['forum_id' => $forum->id]
            );
        }


        // Notify the forum owner if not the same person
        if ((int)$forum->user_id !== (int)$user->id || $comment->user_type !== get_class($user)) {
            $forumOwner = $forum->user;
            if ($forumOwner instanceof User) {
                $forumOwner->sendNotification(
                    'forum_comment_received',
                    'New Comment on Your Forum',
                    "{$user->first_name} {$user->last_name} commented on your forum <strong>{$forum->title}</strong>: \"{$comment->comment}\"",
                    [
                        'forum_id'   => $forum->id,
                        'comment_id' => $comment->id,
                        'from_user'  => $user->id,
                    ]
                );
            } else if ($forumOwner instanceof Admin) {
                $this->createAdminNotification(
                    $forumOwner->id,
                    'forum_comment_received',
                    'New Comment on Your Forum',
                    "{$user->first_name} {$user->last_name} commented on your forum <strong>{$forum->title}</strong>: \"{$comment->comment}\"",
                    [
                        'forum_id'   => $forum->id,
                        'comment_id' => $comment->id,
                        'from_user'  => $user->id,
                    ]
                );
            }
        }

        // If this is a reply, notify the parent comment owner
        if ($request->parent_id) {
            $parentComment = $forum->comments()->find($request->parent_id);

            if ($parentComment && (int)$parentComment->user_id !== (int)$user->id) {

                $parentOwner = $parentComment->user;
                if ($parentOwner instanceof User) {
                    $parentOwner->sendNotification(
                        'forum_comment_reply',
                        'Reply to Your Comment',
                        "{$user->first_name} {$user->last_name} replied to your comment on <strong>{$forum->title}</strong>: \"{$comment->comment}\"",
                        [
                            'forum_id'     => $forum->id,
                            'comment_id'   => $comment->id,
                            'parent_id'    => $parentComment->id,
                            'from_user'    => $user->id,
                        ]
                    );
                } else if ($parentOwner instanceof Admin) {
                    $this->createAdminNotification(
                        $parentOwner->id,
                        'forum_comment_reply',
                        'Reply to Your Comment',
                        "{$user->first_name} {$user->last_name} replied to your comment on <strong>{$forum->title}</strong>: \"{$comment->comment}\"",
                        [
                            'forum_id'     => $forum->id,
                            'comment_id'   => $comment->id,
                            'parent_id'    => $parentComment->id,
                            'from_user'    => $user->id,
                        ]
                    );
                }
            }
        }

        return response()->json([
            'message' => 'Comment added successfully.',
            'comment' => $comment->load('user'),
        ], 200);
    }


    /**
     * Fetch comments with nested replies
     */
    public function fetchComments($forumId)
    {
        $user = auth('user')->user() ?: auth('admin')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $cacheKey = "forum_chat_comment_{$forumId}";
        $comments = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($forumId) {
            return ForumChatComment::where('forum_chat_id', $forumId)
                ->whereNull('parent_id')
                ->where('flag', '!=', 'hidden')
                ->with([
                    // 'user.roles:id,name',
                    // 'user.activeRole:id,name',
                    // 'user.profile:id,user_id,profile_image',
                    'replies' => function ($q) {
                        $q->where('flag', '!=', 'hidden')
                            ->with([
                                // 'user.roles:id,name',
                                // 'user.activeRole:id,name',
                                // 'user.profile:id,user_id,profile_image',
                            ]);
                    }
                ])
                ->latest()
                ->get();
        });
        return response()->json($comments);
    }

    /**
     * Delete comment
     */
    public function deleteComment($commentId)
    {
        $user = auth('user')->user() ?: auth('admin')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $comment = ForumChatComment::findOrFail($commentId);

        // Log::info([
        //     "comment_id" => $commentId,
        //     "comment_user_id" => $comment->user_id,
        //     "comment_user_type" => $comment->user_type,
        //     "userid" => $user->id,
        //     "user_type" => get_class($user),
        //     "info" => "delete"
        // ]);

        if (((int)$comment->user_id !== (int)$user->id) || ($comment->user_type !== get_class($user))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $commentContent = $comment->comment;
        $comment->delete();

        event(new ForumCommentDeleted($commentId, $comment->forum_chat_id));


        if ($user instanceof Admin) {
            $this->createAdminActivity(
                $user->id,
                'forum_comment_delete',
                'Deleted a comment',
                "You deleted your comment: \"{$commentContent}\"",
                ['comment_id' => $commentId]
            );

            $this->createAdminNotification(
                $user->id,
                'forum_comment_deleted',
                'Comment deleted',
                "Your comment <em>\"{$commentContent}\"</em> has been removed.",
                ['comment_id' => $commentId]
            );
        } elseif ($user instanceof User) {
            $user->logActivity(
                'forum_comment_delete',
                'Deleted a comment',
                "You deleted your comment: \"{$commentContent}\"",
                ['comment_id' => $commentId]
            );

            $user->sendNotification(
                'forum_comment_deleted',
                'Comment deleted',
                "Your comment <em>\"{$commentContent}\"</em> has been removed.",
                ['comment_id' => $commentId]
            );
        }

        return response()->json(['message' => 'Comment deleted successfully']);
    }

    /**
     * Edit comment
     */
    public function editComment(Request $request, $commentId)
    {
        $user = auth('user')->user() ?: auth('admin')->user();
        if (!$user) return response()->json(['message' => "Unauthorized"], 403);

        $comment = ForumChatComment::findOrFail($commentId);

        $request->validate([
            'comment' => 'required|string|max:500',
        ]);

        if (((int)$comment->user_id !== (int)$user->id) || ($comment->user_type !== get_class($user))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $oldComment = $comment->comment;
        $comment->update(['comment' => $request->comment]);

        event(new ForumCommentUpdated($comment));


        if ($user instanceof Admin) {
            $this->createAdminActivity(
                $user->id,
                'forum_comment_edit',
                'Edited a comment',
                "You updated your comment from \"{$oldComment}\" to \"{$comment->comment}\"",
                ['comment_id' => $commentId]
            );

            $this->createAdminNotification(
                $user->id,
                'forum_comment_updated',
                'Comment updated',
                "Your comment has been updated to: <em>\"{$comment->comment}\"</em>.",
                ['comment_id' => $commentId]
            );
        } elseif ($user instanceof User) {
            $user->logActivity(
                'forum_comment_edit',
                'Edited a comment',
                "You updated your comment from \"{$oldComment}\" to \"{$comment->comment}\"",
                ['comment_id' => $commentId]
            );

            $user->sendNotification(
                'forum_comment_updated',
                'Comment updated',
                "Your comment has been updated to: <em>\"{$comment->comment}\"</em>.",
                ['comment_id' => $commentId]
            );
        }

        return response()->json([
            'message' => 'Comment updated successfully',
            'comment' => $comment
        ], 200);
    }

    /**
     * User flag
     */
    public function flag(Request $request, $id)
    {
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 403);
        }

        $request->validate([
            'flag' => 'required|in:spam,abuse',
        ]);

        $comment = ForumChatComment::findOrFail($id);

        $key = "user:{$user->id}:forum_comment:{$comment->id}:flag";
        if (Cache::has($key)) {
            return response()->json([
                'message' => 'You are flagging too frequently. Please wait before retrying.'
            ], 429);
        }
        Cache::put($key, true, now()->addMinutes(2));

        $oldFlag = $comment->flag;
        $comment->update(['flag' => $request->flag]);

        $user->logActivity(
            'forum_comment_flagged',
            'You flagged a comment',
            "You flagged comment #{$comment->id} (forum #{$comment->forum_chat_id}) as '{$request->flag}'. Previous flag: '{$oldFlag}'.",
            [
                'comment_id' => $comment->id,
                'forum_id'   => $comment->forum_chat_id,
                'flag'       => $request->flag,
            ]
        );

        $user->sendNotification(
            'forum_comment_flag',
            'Comment flagged',
            "You flagged a forum comment as <strong>{$request->flag}</strong>. Our moderators may review this action.",
            [
                'comment_id' => $comment->id,
                'forum_id'   => $comment->forum_chat_id,
                'flag'       => $request->flag,
            ]
        );

        // notify all admins
        $admins = Admin::all();
        foreach ($admins as $admin) {
            $this->createAdminActivity(
                $admin->id,
                'forum_comment_flagged',
                'Forum comment flagged',
                "User {$user->first_name} {$user->last_name} flagged comment #{$comment->id} (forum #{$comment->forum_chat_id}) as '{$request->flag}'. Previous flag: '{$oldFlag}'.",
                [
                    'comment_id' => $comment->id,
                    'forum_id'   => $comment->forum_chat_id,
                    'user_id'    => $user->id,
                    'flag'       => $request->flag
                ]
            );

            $this->createAdminNotification(
                $admin->id,
                'Forum Comment Flagged',
                'forum_comment_flag',
                "Comment #{$comment->id} was flagged as '{$request->flag}' by {$user->first_name} {$user->last_name}.",
                [
                    'comment_id' => $comment->id,
                    'forum_id'   => $comment->forum_chat_id,
                    'user_id'    => $user->id,
                    'flag'       => $request->flag
                ]
            );
        }

        return response()->json(['message' => 'Comment flagged as ' . $request->flag]);
    }

    /**
     * Admin flag
     */
    public function updateFlag(Request $request, $id)
    {
        $actor = auth('admin')->user();
        if (!$actor) return response()->json(['message' => 'Unauthorized'], 403);

        $request->validate([
            'flag' => 'required|in:none,spam,abuse,hidden',
        ]);

        $comment = ForumChatComment::findOrFail($id);

        $key = "admin:{$actor->id}:forum_comment:{$comment->id}:flag";
        if (Cache::has($key)) {
            return response()->json([
                'message' => 'You are performing this action too frequently. Please wait before retrying.'
            ], 429);
        }
        Cache::put($key, true, now()->addMinute());

        $oldFlag = $comment->flag;
        $comment->update(['flag' => $request->flag]);

        $admins = Admin::all();
        foreach ($admins as $admin) {
            $this->createAdminActivity(
                $admin->id,
                'forum_comment_flag_updated',
                'Forum comment flag updated',
                "Admin {$actor->first_name} {$actor->last_name} set flag '{$request->flag}' on forum comment #{$comment->id} (forum #{$comment->forum_chat_id}), previous flag: '{$oldFlag}'.",
                [
                    'comment_id' => $comment->id,
                    'forum_id'   => $comment->forum_chat_id,
                    'actor_id'   => $actor->id,
                    'flag'       => $request->flag
                ]
            );

            $this->createAdminNotification(
                $admin->id,
                'Forum Comment Flag Updated',
                'forum_comment_flag',
                "Forum comment #{$comment->id} was marked as '{$request->flag}' by {$actor->first_name} {$actor->last_name}.",
                [
                    'comment_id' => $comment->id,
                    'forum_id'   => $comment->forum_chat_id,
                    'flag'       => $request->flag
                ]
            );
        }

        if ($comment->user_type === User::class) {
            $owner = User::find($comment->user_id);
            if ($owner) {
                $owner->logActivity(
                    'forum_comment_flagged_by_admin',
                    'Forum comment flagged by admin',
                    "Admin {$actor->first_name} {$actor->last_name} updated your forum comment #{$comment->id} to '{$request->flag}'.",
                    ['comment_id' => $comment->id, 'flag' => $request->flag]
                );

                $owner->sendNotification(
                    'forum_comment_flagged_admin',
                    'Your forum comment was flagged',
                    "An administrator marked your forum comment as <strong>{$request->flag}</strong>. Contact support if this is a mistake.",
                    ['comment_id' => $comment->id, 'flag' => $request->flag, 'admin_id' => $actor->id]
                );
            }
        }

        return response()->json([
            'message' => 'Forum comment flag updated by admin',
            'comment' => $comment
        ], 200);
    }
}
