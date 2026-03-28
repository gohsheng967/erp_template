<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Str;

class PurchaseRequest extends Model
{
    protected $fillable = [
        'code',
        'title',
        'purpose',
        'department_id',
        'project_id',
        'is_subcon_purchase_request',
        'branch_id',
        'requested_by',
        'submitted_at',
        'status',
        'approved_quotation_id',
        'requester_remark',
        'reviewer',
        'reviewer_remark',
        'remark_log',
        'required_date',
        'approved_at',
        'delivery_period',
        'payment_terms',
        'site_contact_user_id',
    ];

    protected $casts = [
        'remark_log' => 'array',
        'is_subcon_purchase_request' => 'boolean',
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

    public function siteContact()
    {
        return $this->belongsTo(User::class, 'site_contact_user_id');
    }

    public function items()
    {
        $relation = $this->hasMany(PurchaseRequestItem::class);
        if (Schema::hasColumn('purchase_request_items', 'parent_id')) {
            $relation->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END');
        }

        return $relation->orderBy('id');
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

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    protected $appends = ['total_amount'];

    public function getTotalAmountAttribute()
    {
        return $this->items->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });
    }
}
