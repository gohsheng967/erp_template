<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $guarded = [];

    public const FIXED_ROLE_NAMES = [
        'General Manager',
        'Department Head',
        'Staff',
    ];

    public static function fixedRoleNames(): array
    {
        return self::FIXED_ROLE_NAMES;
    }

    public const DEPARTMENT_ROLE_NAMES = [
        'Department Head',
        'Staff',
    ];

    public static function departmentRoleNames(): array
    {
        return self::DEPARTMENT_ROLE_NAMES;
    }
    
    public function users()
    {
        return $this->belongsToMany(User::class, 'pivot_user_departments')
            ->withPivot('department_id')
            ->withTimestamps();
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'pivot_department_role');
    }


    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'pivot_role_permissions');
    }

}
