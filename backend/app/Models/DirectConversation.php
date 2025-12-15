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

    public static function findOrCreateBetween(User $userA, User $userB): self
    {
        $conversation = self::where(function ($query) use ($userA, $userB) {
            $query->where('user_one_id', $userA->id)
                  ->where('user_two_id', $userB->id);
        })->orWhere(function ($query) use ($userA, $userB) {
            $query->where('user_one_id', $userB->id)
                  ->where('user_two_id', $userA->id);
        })->first();

        if (!$conversation) {
            $conversation = self::create([
                'user_one_id' => $userA->id,
                'user_two_id' => $userB->id,
                'user_one_accepted' => false,
                'user_two_accepted' => false,
            ]);
        }

        return $conversation;
    }
}
