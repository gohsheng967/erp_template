<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'company_name',
        'address',
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

    public function projects()
    {
        return $this->hasMany(Project::class, 'client_id');
    }

    public function inventoryAllocations()
    {
        return $this->morphMany(InventoryAllocation::class, 'allocatable');
    }

    public function arInvoices()
    {
        return $this->hasMany(ArInvoice::class);
    }
}
