<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'item_name',
        'description',
        'quantity',
        'unit_price',
        'delivered_quantity',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function deliveryItems()
    {
        return $this->hasMany(PurchaseDeliveryItem::class);
    }
}

