<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SubConTaskUpdate extends Model
{
    protected $fillable = [
        'uuid',
        'sub_con_task_id',
        'progress_percent',
        'note',
        'attachment_path',
        'attachment_name',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function task()
    {
        return $this->belongsTo(SubConTask::class, 'sub_con_task_id');
    }
}
