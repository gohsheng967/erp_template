<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'parent_id',
        'allowed_extensions',
        'max_size',
        'visibility',
        'allowed_departments',
        'allowed_roles'
    ];

    protected $casts = [
        'allowed_extensions' => 'array',
        'allowed_departments' => 'array',
        'allowed_roles' => 'array'
    ];

    public function parent()
    {
        return $this->belongsTo(FileCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(FileCategory::class, 'parent_id');
    }
}
