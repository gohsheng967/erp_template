<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryStock extends Model
{
    protected $fillable = [
        'warehouse_id',
        'purchase_order_item_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    /* =========================
       RELATIONSHIPS
    ========================= */

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
