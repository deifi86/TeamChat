<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private string $messageableType;
    private int $messageableId;
    private int $messageId;

    public function __construct(Message $message)
    {
        // Daten speichern bevor die Message gelöscht wird
        $this->messageableType = $message->messageable_type;
        $this->messageableId = $message->messageable_id;
        $this->messageId = $message->id;
    }

    public function broadcastOn(): array
    {
        $channelName = $this->messageableType === 'channel'
            ? 'channel.' . $this->messageableId
            : 'conversation.' . $this->messageableId;

        return [new PrivateChannel($channelName)];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->messageId,
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }
}
