<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

use Str;

class PurchaseQuotation extends Model
{
    protected $fillable = [
        'purchase_request_id',
        'supplier_id',
        'supplier_name',
        'supplier_pic_name',
        'supplier_pic_contact',
        'amount',
        'currency',
        'delivery_time',
        'terms',
        'is_selected',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function purchaseRequests()
    {
        return $this->belongsToMany(
            PurchaseRequest::class,
            'pivot_purchase_request_quotations'
        )->withTimestamps();
    }


    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->hasOne(PurchaseOrder::class);
    }

    public function attachment()
    {
        return $this->morphOne(Attachment::class, 'attachable');
    }
}

