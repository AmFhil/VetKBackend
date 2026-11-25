<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProfileUpdatedListener implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ProfileUpdated $event): void
    {
        //
        $user = $event->user;
        $user->logActivity('update', 'Profile Updated', 'User updated their profile');
        $user->sendNotification('profile', 'Profile Updated', 'Your profile was successfully updated.')->queue();
    }
}
