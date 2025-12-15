<?php

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionRemoved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public string $emoji,
        public User $user
    ) {}

    public function broadcastOn(): array
    {
        $channelName = $this->message->messageable_type === 'channel'
            ? 'channel.' . $this->message->messageable_id
            : 'conversation.' . $this->message->messageable_id;

        return [new PrivateChannel($channelName)];
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'emoji' => $this->emoji,
            'user_id' => $this->user->id,
        ];
    }

    public function broadcastAs(): string
    {
        return 'reaction.removed';
    }
}
