<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'email',
        'password',
        'username',
        'avatar_path',
        'status',
        'status_text',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationships
    public function ownedCompanies()
    {
        return $this->hasMany(Company::class, 'owner_id');
    }

    public function companyMemberships()
    {
        return $this->hasMany(CompanyMember::class);
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_members')
            ->withPivot('role', 'joined_at');
    }

    public function channelMemberships()
    {
        return $this->hasMany(ChannelMember::class);
    }

    public function channels()
    {
        return $this->belongsToMany(Channel::class, 'channel_members')
            ->withPivot('added_by', 'joined_at');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function uploadedFiles()
    {
        return $this->hasMany(File::class, 'uploader_id');
    }

    public function directConversationsAsUserOne()
    {
        return $this->hasMany(DirectConversation::class, 'user_one_id');
    }

    public function directConversationsAsUserTwo()
    {
        return $this->hasMany(DirectConversation::class, 'user_two_id');
    }

    // Accessors
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar_path) {
            return null;
        }

        return asset('storage/' . $this->avatar_path);
    }

    // Helper Methods
    public function isMemberOf(Company $company): bool
    {
        return $this->companies()->where('company_id', $company->id)->exists();
    }

    public function isAdminOf(Company $company): bool
    {
        $membership = $this->companies()
            ->where('company_id', $company->id)
            ->first();

        return $membership && $membership->pivot->role === 'admin';
    }

    public function isMemberOfChannel(Channel $channel): bool
    {
        return $this->channels()->where('channel_id', $channel->id)->exists();
    }
}
