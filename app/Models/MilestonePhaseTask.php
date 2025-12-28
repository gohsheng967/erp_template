<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilestonePhaseTask extends Model
{
    protected $fillable = [
        'milestone_phase_id',
        'title',
        'is_done',
        'position',
    ];

    protected $casts = [
        'is_done' => 'boolean',
    ];

    /* =========================
       RELATIONSHIPS
    ========================= */

    public function phase(): BelongsTo
    {
        return $this->belongsTo(MilestonePhase::class, 'milestone_phase_id');
    }
}
