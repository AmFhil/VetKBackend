<?php

namespace App\Events;

use App\Models\AdminNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminNotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;

    public function __construct(AdminNotification $notification)
    {
        $this->notification = $notification;
    }

    public function broadcastOn()
    {
        // Private channel for the specific admin
        return new PrivateChannel("App.Models.Admin.{$this->notification->admin_id}");
    }

    public function broadcastWith()
    {
        return [
            'id'       => $this->notification->id,
            'title'    => $this->notification->title,
            'type'     => $this->notification->type,
            'content'  => $this->notification->content,
            'is_read'  => $this->notification->is_read,
            'read_at'  => $this->notification->read_at,
            'meta'     => $this->notification->meta,
            'created_at' => $this->notification->created_at->toDateTimeString(),
        ];
    }

    public function broadcastAs(){
        return "AdminNotification";
    }
}
