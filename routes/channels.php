<?php

use App\Models\BlogChat;
use App\Models\ForumChat;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Support\Facades\Broadcast;

// private channels for users
Broadcast::channel('App.Models.User.{id}', function ($authenticated, $id) {
    if ($authenticated instanceof User) {
        return (int) $authenticated->id === (int) $id;
    }
    return false;
});

// for admins
Broadcast::channel('App.Models.Admin.{id}', function ($authenticated, $id) {
    if ($authenticated instanceof Admin) {
        return (int) $authenticated->id === (int) $id;
    }
    return false;
});

// for forums
Broadcast::channel('forum.{forumId}', function ($authenticated, $forumId) {
    if ($authenticated instanceof User || $authenticated instanceof Admin) {
        return ForumChat::where('id', $forumId)->exists();
    }
    return false;
});

// for blogs
Broadcast::channel('blog.{blogId}', function ($authenticated, $blogId) {
    if ($authenticated instanceof User || $authenticated instanceof Admin) {
        return BlogChat::where('id', $blogId)->exists();
    }
    return false;
});

Broadcast::channel('direct-chat.{user1Id}.{user2Id}', function ($authenticated, $user1Id, $user2Id) {
    if ($authenticated instanceof User) {  // Assuming DMs are user-only;
        return (int) $authenticated->id === (int) $user1Id || (int) $authenticated->id === (int) $user2Id;
    }
    return false;
});