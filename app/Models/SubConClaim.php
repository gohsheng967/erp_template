<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SubConClaim extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'claim_no',
        'project_id',
        'sub_con_id',
        'purchase_order_id',
        'title',
        'status',
        'claimed_amount',
        'project_verified_amount',
        'contra_verified_amount',
        'approved_amount',
        'payment_slip_no',
        'appeal_round',
        'proforma_invoice_path',
        'proforma_invoice_name',
        'proof_attachment_path',
        'proof_attachment_name',
        'proof_attachments',
        'real_invoice_path',
        'real_invoice_name',
        'real_invoice_no',
        'real_invoice_date',
        'real_invoice_amount',
        'submitted_at',
        'project_verified_at',
        'contra_verified_at',
        'approved_at',
        'payment_slip_prepared_at',
        'subcon_decided_at',
        'real_invoice_uploaded_at',
        'remark_log',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'claimed_amount' => 'decimal:2',
        'project_verified_amount' => 'decimal:2',
        'contra_verified_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'real_invoice_amount' => 'decimal:2',
        'real_invoice_date' => 'date',
        'submitted_at' => 'datetime',
        'project_verified_at' => 'datetime',
        'contra_verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'payment_slip_prepared_at' => 'datetime',
        'subcon_decided_at' => 'datetime',
        'real_invoice_uploaded_at' => 'datetime',
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

    public function subCon()
    {
        return $this->belongsTo(SubCon::class, 'sub_con_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function paymentSlip()
    {
        return $this->morphOne(PaymentSlip::class, 'source');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
