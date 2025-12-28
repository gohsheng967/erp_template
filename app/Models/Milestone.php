<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Milestone extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'start_date',
        'end_date',
        'status',
        'progress',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];


    /* =========================
       RELATIONSHIPS
    ========================= */

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // Section 1 — Immediate tasks
    public function actionTasks(): HasMany
    {
        return $this->hasMany(MilestoneActionTask::class);
    }

    // Section 2 — Planning phases
    public function phases(): HasMany
    {
        return $this->hasMany(MilestonePhase::class)
                    ->orderBy('position');
    }
}
