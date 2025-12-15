<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'join_password',
        'owner_id',
        'logo_path',
    ];

    protected $hidden = [
        'join_password',
    ];

    // Relationships
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members()
    {
        return $this->hasMany(CompanyMember::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'company_members')
            ->withPivot('role', 'joined_at');
    }

    public function channels()
    {
        return $this->hasMany(Channel::class);
    }

    // Accessors
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return asset('storage/' . $this->logo_path);
    }

    // Methods
    public function addMember(User $user, string $role = 'user'): void
    {
        if ($this->users()->where('user_id', $user->id)->exists()) {
            return;
        }

        $this->users()->attach($user->id, [
            'role' => $role,
            'joined_at' => now(),
        ]);
    }

    public function removeMember(User $user): void
    {
        $this->users()->detach($user->id);

        // Entferne User aus allen Channels dieser Firma
        foreach ($this->channels as $channel) {
            $channel->members()->detach($user->id);
        }
    }

    public function checkJoinPassword(string $password): bool
    {
        return \Illuminate\Support\Facades\Hash::check($password, $this->join_password);
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($company) {
            if (!$company->slug) {
                $company->slug = \Illuminate\Support\Str::slug($company->name);
            }
        });

        static::updating(function ($company) {
            if ($company->isDirty('name')) {
                $company->slug = \Illuminate\Support\Str::slug($company->name);
            }
        });
    }
}
