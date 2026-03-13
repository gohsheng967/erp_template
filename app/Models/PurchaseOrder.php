<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Str;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'code',
        'purchase_request_id',
        'branch_id',
        'purchase_quotation_id',
        'supplier_id',
        'total_amount',
        'currency',
        'order_date',
        'expected_delivery_date',
        'delivery_period',
        'payment_terms',
        'site_contact_user_id',
        'status',
        'terms',
        'remark',
        'approved_at',
        'approver_id',
        'confirmed_at',
        'confirmed_by'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
    
    protected $casts = [
        'terms' => 'array',
    ];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function quotation()
    {
        return $this->belongsTo(
            PurchaseQuotation::class,
            'purchase_quotation_id'
        );
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function deliveries()
    {
        return $this->hasMany(PurchaseDelivery::class);
    }

    public function confirmBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function signedPo()
    {
        return $this->morphOne(Attachment::class, 'attachable');
    }

    public function apInvoice()
    {
        return $this->hasOne(ApInvoice::class);
    }

    public function siteContact()
    {
        return $this->belongsTo(User::class, 'site_contact_user_id');
    }

}
