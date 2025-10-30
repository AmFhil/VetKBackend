<?php

namespace App\Traits;

use App\Models\UserActivity;
use App\Models\UserNotification;
use App\Events\UserActivityEvent;
use App\Events\UserNotificationEvent;

trait HasUserEvents
{
    /**
     * Log an activity for the user
     */
    public function logActivity(string $action, string $title, ?string $detail = null, array $meta = []): UserActivity
    {
        $activity = $this->activities()->create([
            'action' => $action,
            'title'  => $title,
            'detail' => $detail,
            'meta'   => $meta,
        ]);

        broadcast(new UserActivityEvent($activity));

        return $activity;
    }

    /**
     * Send a notification to the user
     */
    public function sendNotification(string $type, string $title, ?string $content = null, array $meta = []): UserNotification
    {
        $notification = $this->notifications()->create([
            'type'    => $type,
            'title'   => $title,
            'content' => $content,
            'meta'    => $meta,
        ]);

        broadcast(new UserNotificationEvent($notification));

        return $notification;
    }
}
