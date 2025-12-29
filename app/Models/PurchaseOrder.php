<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Str;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'code',
        'purchase_request_id',
        'purchase_quotation_id',
        'supplier_id',
        'total_amount',
        'currency',
        'order_date',
        'expected_delivery_date',
        'status',
        'terms',
        'remark',
        'approved_at',
        'approver_id',
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

    public function quotation()
    {
        return $this->belongsTo(
            PurchaseQuotation::class,
            'purchase_quotation_id'
        );
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function deliveries()
    {
        return $this->hasMany(PurchaseDelivery::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
