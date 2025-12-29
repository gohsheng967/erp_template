<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Str;

class PurchaseRequest extends Model
{
    protected $fillable = [
        'code',
        'title',
        'purpose',
        'department_id',
        'project_id',
        'requested_by',
        'submitted_at',
        'status',
        'approved_quotation_id',
        'requester_remark',
        'reviewer',
        'reviewer_remark',
        'required_date',
        'approved_at'
    ];
    
    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

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
        return $this->hasOne(PurchaseOrder::class, 'purchase_request_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'reviewer');
    }

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function recalcTotal(): void
    {
        $this->updateQuietly([
            'total_amount' => $this->items()->sum('total_price'),
        ]);
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    protected $appends = ['total_amount'];

    public function getTotalAmountAttribute()
    {
        return $this->items->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });
    }
}
