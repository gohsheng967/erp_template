<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $guarded = [];

    public const FIXED_DEPARTMENT_NAMES = [
        'Contract',
        'Account',
        'Purchasing',
        'Project',
        'Admin',
    ];

    public static function fixedDepartmentNames(): array
    {
        return self::FIXED_DEPARTMENT_NAMES;
    }
    
    public function users()
    {
        return $this->belongsToMany(User::class, 'pivot_user_departments')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'pivot_department_role');
    }

}
