<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'is_private',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
        ];
    }

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(ChannelMember::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'channel_members')
            ->withPivot('added_by', 'joined_at');
    }

    public function messages()
    {
        return $this->morphMany(Message::class, 'messageable');
    }

    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function joinRequests()
    {
        return $this->hasMany(ChannelJoinRequest::class);
    }
}
