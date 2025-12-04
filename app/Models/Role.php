<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $guarded = [];
    
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
