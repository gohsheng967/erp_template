<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'code',
        'purchase_request_id',
        'purchase_quotation_id',
        'supplier_name',
        'total_amount',
        'currency',
        'order_date',
        'expected_delivery_date',
        'status',
        'terms',
        'remark',
    ];

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

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function deliveries()
    {
        return $this->hasMany(PurchaseDelivery::class);
    }
}

