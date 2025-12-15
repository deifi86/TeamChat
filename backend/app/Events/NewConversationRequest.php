<?php

namespace App\Events;

use App\Models\DirectConversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewConversationRequest implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DirectConversation $conversation,
        public User $initiator,
        public User $receiver
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->receiver->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'initiator' => [
                'id' => $this->initiator->id,
                'username' => $this->initiator->username,
                'avatar_url' => $this->initiator->avatar_url,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.request';
    }
}
