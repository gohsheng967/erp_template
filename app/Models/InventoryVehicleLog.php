<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryVehicleLog extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'trip_date',
        'started_at',
        'ended_at',
        'mileage',
        'start_mileage',
        'end_mileage',
        'origin',
        'destination',
        'purpose',
        'trip_status',
        'pin_bypassed',
        'bound_to_type',
        'bound_to_project_id',
        'bound_to_label',
        'driver_user_id',
        'driver_name',
        'created_by',
    ];

    protected $casts = [
        'trip_date' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'mileage' => 'decimal:1',
        'start_mileage' => 'decimal:1',
        'end_mileage' => 'decimal:1',
        'pin_bypassed' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'bound_to_project_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
