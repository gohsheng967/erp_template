<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryInsurance extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'provider',
        'policy_number',
        'start_date',
        'expiry_date',
        'coverage_amount',
        'document_id',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'expiry_date' => 'date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
