<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ForumLiked implements ShouldBroadcast
{
    use Dispatchable, SerializesModels, InteractsWithSockets;

    public $forumId;
    public $user;

    public function __construct($forumId, $user)
    {
        $this->forumId = $forumId;
        $this->user = $user;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('forum.' . $this->forumId);
    }

    public function broadcastAs()
    {
        return 'ForumLiked';
    }
}

