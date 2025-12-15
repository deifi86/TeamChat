<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public string $decryptedContent
    ) {}

    public function broadcastOn(): array
    {
        $channelName = $this->message->messageable_type === 'channel'
            ? 'channel.' . $this->message->messageable_id
            : 'conversation.' . $this->message->messageable_id;

        return [
            new PrivateChannel($channelName),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'content' => $this->decryptedContent,
            'content_type' => $this->message->content_type,
            'sender' => [
                'id' => $this->message->sender->id,
                'username' => $this->message->sender->username,
                'avatar_url' => $this->message->sender->avatar_url,
            ],
            'parent_id' => $this->message->parent_id,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.new';
    }
}
