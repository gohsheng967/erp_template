<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'warehouse_id',
        'purchase_order_item_id',
        'type',
        'quantity',
        'balance_after',
        'reference_type',
        'reference_id',
        'remark',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    /* =========================
       CONSTANTS
    ========================= */

    public const TYPE_IN       = 'IN';
    public const TYPE_OUT      = 'OUT';
    public const TYPE_TRANSFER = 'TRANSFER';
    public const TYPE_ADJUST   = 'ADJUST';

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

    public function reference()
    {
        return $this->morphTo();
    }
}
