<?php

namespace App\Events;

use App\Models\DirectMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DirectMessageSent implements ShouldQueue, ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(DirectMessage $message)
    {
        //
        $this->message = $message->load('sender.profile', 'receiver.profile', 'sender', 'receiver');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $userIds = [$this->message->sender_id, $this->message->receiver_id];
        sort($userIds);  // Sort to standardize the channel name (e.g., always smaller ID first)

        return [
            new PrivateChannel('direct-chat.' . implode('.', $userIds))
        ];
    }

    public function broadcastAs()
    {
        return 'direct-message.sent';
    }
}
