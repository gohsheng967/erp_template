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
        'task_no',
        'project_id',
        'sub_con_id',
        'parent_id',
        'title',
        'amount',
        'invoice_no',
        'invoice_date',
        'invoice_amount',
        'invoice_remark',
        'invoice_attachment_path',
        'invoice_attachment_name',
        'invoice_submitted_at',
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
        'invoice_date' => 'date',
        'invoice_amount' => 'decimal:2',
        'progress_percent' => 'integer',
        'invoice_submitted_at' => 'datetime',
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

    public function refreshProgressFromChildren(): void
    {
        if ($this->parent_id) {
            return;
        }

        $childrenQuery = $this->children();
        $totalChildren = (clone $childrenQuery)->count();

        // Keep manual progress behavior when there are no child tasks.
        if ($totalChildren === 0) {
            return;
        }

        $doneChildren = (clone $childrenQuery)
            ->where('status', '!=', 'draft')
            ->count();

        $progress = (int) round(($doneChildren / $totalChildren) * 100);

        $this->forceFill([
            'progress_percent' => $progress,
        ])->save();
    }

    public static function refreshParentProgressFor(?int $parentId): void
    {
        if (!$parentId) {
            return;
        }

        $parent = static::query()->whereKey($parentId)->first();
        if (!$parent) {
            return;
        }

        $parent->refreshProgressFromChildren();
    }
}
