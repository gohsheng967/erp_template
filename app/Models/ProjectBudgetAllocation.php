<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectBudgetAllocation extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'previous_budget',
        'add_on_amount',
        'new_budget',
        'reason',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
