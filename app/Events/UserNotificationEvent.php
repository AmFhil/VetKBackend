<?php

namespace App\Events;

use App\Models\UserNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class UserNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;

    public function __construct(UserNotification $notification)
    {
        $this->notification = $notification;
    }

    public function broadcastOn()
    {
        // Private channel per user
        return new PrivateChannel('App.Models.User.' . $this->notification->user_id);
    }

    public function broadcastWith()
    {
        return [
            'id'      => $this->notification->id,
            'title'   => $this->notification->title,
            'type'    => $this->notification->type,
            'content' => $this->notification->content,
            'meta'    => $this->notification->meta,
            'is_read' => $this->notification->is_read,
            'created_at' => $this->notification->created_at->toDateTimeString(),
        ];
    }

    public function broadcastAs()
    {
        return "UserNotification";
    }
}
