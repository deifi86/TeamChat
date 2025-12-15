<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'uploader_id',
        'fileable_type',
        'fileable_id',
        'original_name',
        'stored_name',
        'mime_type',
        'file_size',
        'file_path',
        'thumbnail_path',
        'is_compressed',
        'original_size',
    ];

    protected function casts(): array
    {
        return [
            'is_compressed' => 'boolean',
            'file_size' => 'integer',
            'original_size' => 'integer',
        ];
    }

    // Relationships
    public function fileable()
    {
        return $this->morphTo();
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
}
