<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageEdited implements ShouldBroadcast
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

        return [new PrivateChannel($channelName)];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'content' => $this->decryptedContent,
            'edited_at' => $this->message->edited_at->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.edited';
    }
}
