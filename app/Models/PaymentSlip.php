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
        'less_recoupment',
        'less_material_ob',
        'less_paid_previously',
        'payment_slip_remark',
        'created_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
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
}
