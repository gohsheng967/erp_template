<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company_name',
        'address',
    ];

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
