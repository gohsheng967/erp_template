<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySaman extends Model
{
    protected $table = 'inventory_saman';

    protected $fillable = [
        'inventory_item_id',
        'offense_name',
        'source',
        'reference_no',
        'offence_date',
        'amount',
        'status',
        'paid_at',
        'document_id',
    ];

    protected $casts = [
        'offence_date' => 'date',
        'paid_at'      => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
