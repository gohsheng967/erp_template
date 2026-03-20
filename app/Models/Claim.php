<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; 
use Illuminate\Database\Eloquent\SoftDeletes;
use Str;

class Claim extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_CHECKED = 'checked';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CEO_APPROVED = 'ceo_approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED = 'rejected';

    public const REMARK_PETTY_CASH_ORIGIN = 'Created from Petty Cash';

    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($claim) {
            if (empty($claim->uuid)) {
                $claim->uuid = (string) Str::uuid();
            }
        });
    }

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i',
        'remark_log' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(ClaimItem::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function paymentSlip()
    {
        return $this->morphOne(PaymentSlip::class, 'source');
    }

    public static function paymentStatuses(): array
    {
        return [
            self::STATUS_CEO_APPROVED,
            self::STATUS_PAID,
        ];
    }

    public function isPettyCashOrigin(): bool
    {
        return strcasecmp(
            trim((string) $this->remark),
            self::REMARK_PETTY_CASH_ORIGIN
        ) === 0;
    }
}
