<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilestoneActionTask extends Model
{
    protected $fillable = [
        'milestone_id',
        'title',
        'priority',
        'assigned_to',
        'is_done',

        // ✅ ADD THESE
        'status',
        'remark',
        'completed_at',
        'reviewed_at',
    ];

    protected $casts = [
        'is_done' => 'boolean',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /* =========================
       RELATIONSHIPS
    ========================= */

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopePending($query)
    {
        return $query
            ->where('is_done', false);
    }

}
