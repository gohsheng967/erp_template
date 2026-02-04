<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryService extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'service_date',
        'item_parts',
        'cost',
        'vendor',
        'notes',
    ];

    protected $casts = [
        'service_date' => 'date',
        'cost' => 'decimal:2',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
