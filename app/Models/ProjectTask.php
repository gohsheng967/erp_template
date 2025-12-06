<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectTask extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'milestone_id', 'title', 'assigned_to', 'deadline', 'status'
    ];

    public function milestone()
    {
        return $this->belongsTo(ProjectMilestone::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
