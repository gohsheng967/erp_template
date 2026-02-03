<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClaimType extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function claimItems()
    {
        return $this->hasMany(ClaimItem::class, 'claim_type', 'code');
    }
}
