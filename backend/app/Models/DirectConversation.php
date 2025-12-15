<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DirectConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'user_one_accepted',
        'user_two_accepted',
    ];

    protected function casts(): array
    {
        return [
            'user_one_accepted' => 'boolean',
            'user_two_accepted' => 'boolean',
        ];
    }

    // Relationships
    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages()
    {
        return $this->morphMany(Message::class, 'messageable');
    }

    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }

    // Helper Methods
    public function hasUser(User $user): bool
    {
        return $this->user_one_id === $user->id || $this->user_two_id === $user->id;
    }

    public function getOtherUser(User $user): User
    {
        if ($this->user_one_id === $user->id) {
            return $this->userTwo;
        }
        return $this->userOne;
    }

    public function isAccepted(): bool
    {
        return $this->user_one_accepted && $this->user_two_accepted;
    }

    public function acceptBy(User $user): void
    {
        if ($this->user_one_id === $user->id) {
            $this->update(['user_one_accepted' => true]);
        } elseif ($this->user_two_id === $user->id) {
            $this->update(['user_two_accepted' => true]);
        }
    }

    public static function findOrCreateBetween(User $initiator, User $receiver): self
    {
        // Sortiere User-IDs für konsistente Speicherung
        $userOneId = min($initiator->id, $receiver->id);
        $userTwoId = max($initiator->id, $receiver->id);

        $conversation = self::where('user_one_id', $userOneId)
            ->where('user_two_id', $userTwoId)
            ->first();

        if (!$conversation) {
            // Initiator hat automatisch accepted=true
            $conversation = self::create([
                'user_one_id' => $userOneId,
                'user_two_id' => $userTwoId,
                'user_one_accepted' => $userOneId === $initiator->id,
                'user_two_accepted' => $userTwoId === $initiator->id,
            ]);
        }

        return $conversation;
    }
}
