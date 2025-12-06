<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectMilestone extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'type',       // claim, task, timeline
        'title',
        'amount',
        'due_date',
        'start_date',
        'end_date',
        'status'      // pending, in_progress, completed, submitted, paid
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks()
    {
        return $this->hasMany(ProjectTask::class, 'milestone_id');
    }
}
