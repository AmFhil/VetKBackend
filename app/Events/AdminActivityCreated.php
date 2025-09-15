<?php

namespace App\Events;

use App\Models\AdminActivity;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class AdminActivityCreated implements ShouldBroadcast
{
    use SerializesModels;

    public $activity;

    public function __construct(AdminActivity $activity)
    {
        $this->activity = $activity;
    }

    public function broadcastOn()
    {
        // Private channel for the specific admin
        return new PrivateChannel("App.Models.Admin.{$this->activity->admin_id}");
    }

    public function broadcastWith()
    {
        return [
            'id'       => $this->activity->id,
            'action'   => $this->activity->action,
            'title'    => $this->activity->title,
            'detail'   => $this->activity->detail,
            'meta'     => $this->activity->meta,
            'created_at' => $this->activity->created_at->toDateTimeString(),
        ];
    }
}
