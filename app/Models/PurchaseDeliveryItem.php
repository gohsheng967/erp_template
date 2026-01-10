<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseDeliveryItem extends Model
{
    protected $guarded = [];

    public function delivery()
    {
        return $this->belongsTo(
            PurchaseDelivery::class,
            'purchase_delivery_id'
        );
    }

    public function orderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'destination');
    }
    
}

