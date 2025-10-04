<?php

namespace App\Http\Controllers;

use App\Events\ForumLiked;
use App\Models\Admin;
use App\Models\ForumChat;
use App\Models\ForumChatLike;
use App\Models\User;
use App\Traits\BroadcastsAdminEvents;
use Illuminate\Http\Request;

class ForumChatLikeController extends Controller
{
    use BroadcastsAdminEvents;

    /**
     * Toggle like/unlike for a forum chat.
     */
    public function toggleLike($forumId)
    {
        $authUser = auth('user')->user() ?: auth('admin')->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $forum = ForumChat::findOrFail($forumId);

        $existingLike = ForumChatLike::where('forum_chat_id', $forum->id)
            ->where('user_id', $authUser->id)
            ->where('user_type', get_class($authUser))
            ->first();

        /** UNLIKE **/
        if ($existingLike) {
            $existingLike->delete();

            event(new ForumLiked($forum->id, $authUser));

            if ($authUser instanceof Admin) {
                $this->createAdminActivity(
                    $authUser->id,
                    'forum_unlike',
                    'Forum Unliked',
                    "You removed your like from the forum '{$forum->title}'.",
                    ['forum_id' => $forum->id, 'action' => 'unlike']
                );
            } elseif ($authUser instanceof User) {
                $authUser->logActivity(
                    'forum_unlike',
                    'Forum Unliked',
                    "You removed your like from the forum '{$forum->title}'.",
                    ['forum_id' => $forum->id, 'action' => 'unlike']
                );
            }

            return response()->json(['message' => 'unliked', 'user_id' => $authUser->id], 200);
        }

        /** LIKE **/
        ForumChatLike::create([
            'forum_chat_id' => $forum->id,
            'user_id'       => $authUser->id,
            'user_type'     => get_class($authUser),
        ]);

        event(new ForumLiked($forum->id, $authUser));

        if ($authUser instanceof Admin) {
            $this->createAdminActivity(
                $authUser->id,
                'forum_like',
                'Forum Liked',
                "You liked the forum '{$forum->title}'.",
                ['forum_id' => $forum->id, 'action' => 'like']
            );
        } elseif ($authUser instanceof User) {
            $authUser->logActivity(
                'forum_like',
                'Forum Liked',
                "You liked the forum '{$forum->title}'.",
                ['forum_id' => $forum->id, 'action' => 'like']
            );
        }

        // Notify owner (if not the liker)
        if ($forum->user_id !== $authUser->id) {
            $forumOwner = $forum->user;
            if ($forumOwner instanceof User) {
                $forumOwner->sendNotification(
                    'forum_liked',
                    'Your Forum Was Liked',
                    "{$authUser->first_name} {$authUser->last_name} liked your forum <strong>{$forum->title}</strong>.",
                    ['forum_id' => $forum->id, 'from_user' => $authUser->id]
                );
            }else if($forumOwner instanceof Admin){
                $this->createAdminNotification(
                    $forumOwner->id,
                    'forum_liked',
                    'Your Forum Was Liked',
                    "{$authUser->first_name} {$authUser->last_name} liked your forum <strong>{$forum->title}</strong>.",
                    ['forum_id' => $forum->id, 'from_user' => $authUser->id]
                );
            }
        }

        return response()->json(['message' => 'liked', 'user_id' => $authUser->id], 200);
    }
}
