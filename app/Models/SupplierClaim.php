<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SupplierClaim extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'claim_no',
        'project_id',
        'supplier_id',
        'purchase_order_id',
        'title',
        'status',
        'claimed_amount',
        'proforma_invoice_path',
        'proforma_invoice_name',
        'proof_attachment_path',
        'proof_attachment_name',
        'proof_attachments',
        'submitted_at',
        'remark_log',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'claimed_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'remark_log' => 'array',
        'proof_attachments' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}

