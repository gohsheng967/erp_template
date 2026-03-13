<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentSlip extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'slip_no',
        'source_type',
        'source_id',
        'company_bank_account_id',
        'amount',
        'payment_date',
        'less_retention',
        'less_retention_label',
        'less_recoupment',
        'less_recoupment_label',
        'less_material_ob',
        'less_material_ob_label',
        'less_paid_previously',
        'less_paid_previously_label',
        'amount_due_label',
        'workflow_status',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejected_reason',
        'payment_slip_remark',
        'remark_label',
        'created_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function companyBankAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
