<?php

use App\Models\BlogChat;
use App\Models\ForumChat;
use App\Models\User;
use App\Models\Admin;
use App\Models\Membership;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

// private channels for users
Broadcast::channel('App.Models.User.{id}', function ($authenticated, $id) {
    if ($authenticated instanceof User) {
        if ((int) $authenticated->id === (int) $id) {
            return true;
        }
    }
    return false;
});

// for admins
Broadcast::channel('App.Models.Admin.{id}', function ($authenticated, $id) {
    if ($authenticated instanceof Admin) {
        if ((int) $authenticated->id === (int) $id) {
            return true;
        }
    }
    return false;
});

// for forums
Broadcast::channel('forum.{forumId}', function ($user, $forumId) {
    return Membership::where([
        'member_id' => $user->id,
        'member_type' => get_class($user),
        'joinable_id' => $forumId,
        'joinable_type' => ForumChat::class,
    ])->exists()
        ? true
        : false;
});

// for blogs
Broadcast::channel('blog.{blogId}', function ($user, $blogId) {
    return Membership::where([
        'member_id' => $user->id,
        'member_type' => get_class($user),
        'joinable_id' => $blogId,
        'joinable_type' => BlogChat::class,
    ])->exists()
        ? true
        : false;
});

Broadcast::channel('direct-chat.{user1Id}.{user2Id}', function ($authenticated, $user1Id, $user2Id) {
    Log::info('CHANNEL AUTH START', [
        'auth_id' => $authenticated?->id ?? 'null',
        'user1Id' => $user1Id,
        'user2Id' => $user2Id,
        'channel' => request()->input('channel_name'),
        'socket_id' => request()->input('socket_id'),
        'user_instance' => get_class($authenticated ?? 'null')
    ]);

    if (!$authenticated instanceof \App\Models\User) {
        Log::warning('CHANNEL AUTH FAILED: Not a User', [
            'instance' => get_class($authenticated ?? 'null'),
            'auth_id' => $authenticated?->id ?? 'null'
        ]);
        return false;
    }

    if ((int) $authenticated->id === (int) $user1Id || (int) $authenticated->id === (int) $user2Id) {
        Log::info('CHANNEL AUTH SUCCESS', [
            'auth_id' => $authenticated->id,
            'user1Id' => $user1Id,
            'user2Id' => $user2Id
        ]);
        return true;
    }

    Log::warning('CHANNEL AUTH FAILED: ID mismatch', [
        'auth_id' => $authenticated->id,
        'user1Id' => $user1Id,
        'user2Id' => $user2Id
    ]);
    return false;
});

BroadCast::channel('notifications.{userId}', function ($authenticated, $userId) {
    if ($authenticated instanceof User){
        if((int)  $authenticated->id() === (int) $userId){
            return true;
        }
        return false;
    }
});