<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $fillable = [
        'uuid',
        'attachable_type',
        'attachable_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    public function attachable()
    {
        return $this->morphTo();
    }

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
