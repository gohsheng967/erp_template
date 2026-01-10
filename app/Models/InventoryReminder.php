<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReminder extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'type',
        'reference_id',
        'trigger_date',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'trigger_date' => 'date',
        'sent_at'      => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
