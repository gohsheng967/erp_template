<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Str;
class ClaimItem extends Model
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
    
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }
}
