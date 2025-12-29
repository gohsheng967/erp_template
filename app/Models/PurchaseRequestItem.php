<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Str;

class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'title',
        'description',
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }
}

