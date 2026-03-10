<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SubCon extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'company_name',
        'phone',
        'email',
        'address',
        'bank',
        'login_identity_no',
        'login_email',
        'login_password',
        'login_status',
        'login_must_change_password',
    ];

    protected $hidden = [
        'login_password',
    ];

    protected $casts = [
        'login_must_change_password' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function tasks()
    {
        return $this->hasMany(SubConTask::class, 'sub_con_id');
    }

    public function bankAccounts()
    {
        return $this->hasMany(SubConBankAccount::class, 'sub_con_id')->orderBy('id');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_sub_cons', 'sub_con_id', 'project_id')
            ->withTimestamps();
    }
}
