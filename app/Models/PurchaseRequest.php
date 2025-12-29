<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    protected $fillable = [
        'code',
        'title',
        'purpose',
        'department_id',
        'requested_by',
        'status',
        'approved_quotation_id',
        'requester_remark',
        'reviewer_remark',
    ];

    public function quotations()
    {
        return $this->belongsToMany(
            PurchaseQuotation::class,
            'pivot_purchase_request_quotations'
        )->withTimestamps();
    }

    public function approvedQuotation()
    {
        return $this->belongsTo(
            PurchaseQuotation::class,
            'approved_quotation_id'
        );
    }

    public function purchaseOrder()
    {
        return $this->hasOne(PurchaseOrder::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
