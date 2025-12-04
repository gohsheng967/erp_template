<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    public function users()
    {
        return $this->belongsToMany(User::class, 'pivot_user_departments')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'pivot_user_departments')
            ->withPivot('user_id')
            ->withTimestamps();
    }

}
