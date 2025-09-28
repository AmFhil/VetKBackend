<?php

use Illuminate\Support\Facades\Broadcast;

// private channels
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// for admins
Broadcast::channel('App.Models.Admin.{id}', function ($admin, $id) {
    return (int) $admin->id === (int) $id;
});
