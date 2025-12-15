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
}
