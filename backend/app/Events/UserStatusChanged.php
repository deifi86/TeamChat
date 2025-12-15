<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user
    ) {}

    public function broadcastOn(): array
    {
        // An alle Firmen broadcasten in denen der User Mitglied ist
        $channels = $this->user->companies->map(function ($company) {
            return new PrivateChannel('company.' . $company->id);
        })->toArray();

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'status' => $this->user->status,
            'status_text' => $this->user->status_text,
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.status_changed';
    }
}
