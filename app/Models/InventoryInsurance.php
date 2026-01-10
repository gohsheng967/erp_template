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
        'coverage_type',
        'start_date',
        'expiry_date',
        'coverage_amount',
        'premium_amount',
        'document_id',
        'status',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'expiry_date' => 'date',
        'coverage_amount' => 'decimal:2',
        'premium_amount'  => 'decimal:2',
    ];

    /* ===============================
       RELATIONSHIPS
    =============================== */

    public function item(): BelongsTo
    {
        return $this->belongsTo(
            InventoryItem::class,
            'inventory_item_id'
        );
    }

    /* ===============================
       SCOPES
    =============================== */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /* ===============================
       ACCESSORS
    =============================== */

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date
            ? $this->expiry_date->isPast()
            : false;
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

    public function getImageUrlAttribute()
    {
        return Storage::url($this->file_path);
    }
}
