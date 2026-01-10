<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRoadtax extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'start_date',
        'expiry_date',
        'amount',
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

    public function attachment()
    {
        return $this->morphOne(Attachment::class, 'attachable')
            ->latestOfMany();
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
