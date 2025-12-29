<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseDelivery extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'delivery_code',
        'delivery_date',
        'status',
        'remark',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseDeliveryItem::class);
    }
}
