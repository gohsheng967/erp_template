<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Attachment;

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
        'destination_user_id',
        'holder_user_id',
        'usage_status',
        'usage_action',
        'usage_remark',
        'usage_updated_by',
        'usage_updated_at',
        'issued_by',
        'issuer_id',
        'issuer_signature_path',
        'issuer_approved_by',
        'issuer_approved_at',
        'user_approved_by',
        'user_approved_at',
        'balance_after',
        'reference_type',
        'reference_id',
        'remark',
        'purpose',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'usage_updated_at' => 'datetime',
        'issuer_approved_at' => 'datetime',
        'user_approved_at' => 'datetime',
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

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issuer_id');
    }

    public function issuerApprover()
    {
        return $this->belongsTo(User::class, 'issuer_approved_by');
    }

    public function userApprover()
    {
        return $this->belongsTo(User::class, 'user_approved_by');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function destinationUser()
    {
        return $this->belongsTo(User::class, 'destination_user_id');
    }

    public function holderUser()
    {
        return $this->belongsTo(User::class, 'holder_user_id');
    }

    public function usageUpdatedBy()
    {
        return $this->belongsTo(User::class, 'usage_updated_by');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
