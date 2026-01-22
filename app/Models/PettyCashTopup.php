<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class PettyCashTopup extends Model
{
    use HasFactory;

    protected $fillable = [
        'topup_no',
        'uuid',
        'wallet_id',
        'bank_account_id',
        'amount',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejected_reason',
        'paid_by',
        'paid_at',
        'payment_ref_no',
        'payment_slip_no',
        'company_bank_account_id',
        'less_retention',
        'less_recoupment',
        'less_material_ob',
        'less_paid_previously',
        'payment_slip_remark',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(fn ($m) => $m->uuid ??= (string) Str::uuid());
    }

    public function wallet()
    {
        return $this->belongsTo(PettyCashWallet::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(UserBankAccount::class, 'bank_account_id');
    }

    public function companyBankAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id');
    }

    public function paymentSlip()
    {
        return $this->morphOne(PaymentSlip::class, 'source');
    }

    public function attachment()
    {
        return $this->morphOne(Attachment::class, 'attachable')
            ->latestOfMany();
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
