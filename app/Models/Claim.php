<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Str;

class Claim extends Model
{
    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($claim) {
            if (empty($claim->uuid)) {
                $claim->uuid = (string) Str::uuid();
            }
        });
    }

    public function items()
    {
        return $this->hasMany(ClaimItem::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
