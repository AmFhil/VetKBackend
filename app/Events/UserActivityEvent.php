<?php

namespace App\Events;

use App\Models\UserActivity;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class UserActivityEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $activity;

    public function __construct(UserActivity $activity)
    {
        $this->activity = $activity;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('App.Models.User.' . $this->activity->user_id);
    }

    public function broadcastWith()
    {
        return [
            'id'        => $this->activity->id,
            'action'    => $this->activity->action,
            'title'     => $this->activity->title,
            'detail'    => $this->activity->detail,
            'meta'      => $this->activity->meta,
            'created_at'=> $this->activity->created_at->toDateTimeString(),
        ];
    }
}
