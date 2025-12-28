<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Str;

class ProjectDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'project_id',
        'user_id',
        'filename',
        'filepath',
        'filesize',
        'type',
        'version',
        'category_id'
    ];

    protected static function booted()
    {
        static::creating(function ($claim) {
            if (empty($claim->uuid)) {
                $claim->uuid = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(FileCategory::class);
    }
}
