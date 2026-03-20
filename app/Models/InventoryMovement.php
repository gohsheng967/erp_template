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
        'serial_number',
        'stock_category',
        'issue_destination_type',
        'project_id',
        'site_id',
        'issued_by',
        'balance_after',
        'reference_type',
        'reference_id',
        'remark',
        'purpose',
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

    public function issueUser()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
