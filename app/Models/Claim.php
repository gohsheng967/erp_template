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

    public function issuer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
