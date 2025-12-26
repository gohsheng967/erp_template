<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected static function booted()
    {
        static::creating(function ($claim) {
            if (empty($claim->uuid)) {
                $claim->uuid = (string) Str::uuid();
            }
        });
    }
}
