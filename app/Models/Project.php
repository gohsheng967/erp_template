<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'client_id', 'start_date', 'end_date',
        'budget', 'department_id', 'manager_id', 'status', 'description'
    ];

    // Documents
    public function documents()
    {
        return $this->hasMany(ProjectDocument::class);
    }

    // Milestones
    public function milestones()
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    // Tasks through Milestones (optional)
    public function tasks()
    {
        return $this->hasManyThrough(ProjectTask::class, ProjectMilestone::class);
    }

    // Claim Expenses (standalone, linked by project_id)
    public function claimExpenses()
    {
        return $this->hasMany(ClaimExpense::class);
    }

    // Purchase Requests (standalone)
    public function purchaseRequests()
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    // Activity Log
    public function activities()
    {
        return $this->hasMany(ProjectActivityLog::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function claims()
    {
        return $this->hasMany(Claim::class, 'project_id');
    }

}
