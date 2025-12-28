<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilestonePhase extends Model
{
    protected $fillable = [
        'milestone_id',
        'title',
        'start_date',
        'end_date',
        'position',
        'status',
        'progress',
        'reviewer',
        'skip_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    /* =========================
       RELATIONSHIPS
    ========================= */

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(MilestonePhaseTask::class)
                    ->orderBy('position');
    }

    /* =========================
       HELPERS (OPTIONAL)
    ========================= */

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }
}
