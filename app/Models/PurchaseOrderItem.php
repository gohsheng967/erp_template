<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Str;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'purchase_request_item_id', // 🔑 VERY IMPORTANT
        'item_name',
        'description',
        'quantity',
        'unit_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
    /* ======================
       RELATIONSHIPS
    ====================== */

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function deliveries()
    {
        return $this->hasMany(PurchaseDeliveryItem::class);
    }

    /* ======================
       DERIVED VALUES
    ====================== */

    public function getDeliveredQuantityAttribute()
    {
        return $this->deliveries()->sum('received_quantity');
    }

    public function getRemainingQuantityAttribute()
    {
        return $this->quantity - $this->delivered_quantity;
    }
}
