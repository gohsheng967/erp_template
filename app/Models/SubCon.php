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
}
