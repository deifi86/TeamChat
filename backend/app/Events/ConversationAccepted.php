<?php

namespace App\Events;

use App\Models\DirectConversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationAccepted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DirectConversation $conversation,
        public User $acceptedBy
    ) {}

    public function broadcastOn(): array
    {
        // An beide Teilnehmer senden
        return [
            new PrivateChannel('user.' . $this->conversation->user_one_id),
            new PrivateChannel('user.' . $this->conversation->user_two_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'accepted_by' => [
                'id' => $this->acceptedBy->id,
                'username' => $this->acceptedBy->username,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.accepted';
    }
}
