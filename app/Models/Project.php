<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Str;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'client_id', 'start_date', 'end_date',
        'extension_date',
        'budget', 'project_value', 'department_id', 'manager_id', 'status',
        'is_finished', 'finished_at', 'description'
    ];

    protected $casts = [
        'extension_date' => 'date',
        'is_finished' => 'boolean',
        'finished_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // Documents
    public function documents()
    {
        return $this->hasMany(ProjectDocument::class);
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
    
    public function milestones()
    {
        return $this->hasMany(Milestone::class);
    }

    public function inventoryAllocations()
    {
        return $this->morphMany(InventoryAllocation::class, 'allocatable');
    }

    public function arInvoices()
    {
        return $this->hasMany(ArInvoice::class, 'project_id');
    }

    public function subConTasks()
    {
        return $this->hasMany(SubConTask::class);
    }

    public function subCons()
    {
        return $this->belongsToMany(SubCon::class, 'project_sub_cons', 'project_id', 'sub_con_id')
            ->withTimestamps();
    }
}
