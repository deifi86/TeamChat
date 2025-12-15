<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'messageable_type',
        'messageable_id',
        'sender_id',
        'content',
        'content_iv',
        'content_type',
        'parent_id',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    // Relationships
    public function messageable()
    {
        return $this->morphTo();
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function parent()
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function readReceipts()
    {
        return $this->hasMany(ReadReceipt::class);
    }
}
