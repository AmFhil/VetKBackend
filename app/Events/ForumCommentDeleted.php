<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ForumCommentDeleted implements ShouldBroadcast
{
    use Dispatchable, SerializesModels, InteractsWithSockets;

    public $commentId;
    public $forumId;

    public function __construct($commentId, $forumId)
    {
        $this->commentId = $commentId;
        $this->forumId = $forumId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('forum.' . $this->forumId);
    }

    public function broadcastAs()
    {
        return 'ForumCommentDeleted';
    }
}

