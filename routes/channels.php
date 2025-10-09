<?php

use App\Models\BlogChat;
use App\Models\ForumChat;
use Illuminate\Support\Facades\Broadcast;

// private channels
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// for admins
Broadcast::channel('App.Models.Admin.{id}', function ($admin, $id) {
    return (int) $admin->id === (int) $id;
});

// for forums
Broadcast::channel('forum.{forumId}', function ($user, $forumId) {
    // restrict to only authenticated user
    return (int) $user->id > 0 && ForumChat::where('id', $forumId)->exists();
});

// for blogs
Broadcast::channel('blog.{blogId}', function ($user, $blogId) {
    return (int) $user->id > 0 && BlogChat::where('id', $blogId)->exists();
});
