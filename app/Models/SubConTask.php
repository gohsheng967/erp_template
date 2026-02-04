<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SubConTask extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'project_id',
        'sub_con_id',
        'parent_id',
        'title',
        'amount',
        'status',
        'progress_percent',
        'payment_cert_no',
        'payment_slip_no',
        'payment_ref_no',
        'verified_at',
        'verified_remark',
        'verified_reject_remark',
        'justified_at',
        'justified_remark',
        'justified_reject_remark',
        'certified_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'progress_percent' => 'integer',
        'verified_at' => 'datetime',
        'justified_at' => 'datetime',
        'certified_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
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

    public function parent()
    {
        return $this->belongsTo(SubConTask::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(SubConTask::class, 'parent_id');
    }

    public function updates()
    {
        return $this->hasMany(SubConTaskUpdate::class)->orderByDesc('id');
    }

    public function paymentSlip()
    {
        return $this->morphOne(PaymentSlip::class, 'source');
    }
}
