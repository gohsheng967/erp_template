<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAllocation extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'allocatable_type',
        'allocatable_id',
        'allocatable_name',
        'location',
        'from_date',
        'to_date',
        'remark',
        'created_by',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date'   => 'date',
    ];

    /* ======================
       RELATIONSHIPS
    ====================== */

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function allocatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ======================
       SCOPES
    ====================== */

    public function scopeActive($query)
    {
        return $query->whereNull('to_date');
    }

    /* ======================
       HELPERS
    ====================== */

    public function displayName(): string
    {
        return $this->allocatable_name
            ?? optional($this->allocatable)->name
            ?? '-';
    }
}
