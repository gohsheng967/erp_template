<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class InventoryItem extends Model
{
    public const TYPE_VEHICLE = 'vehicle';

    public const OWNERSHIP_COMPANY = 'company';
    public const OWNERSHIP_INDIVIDUAL = 'individual';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_DISPOSED = 'disposed';

    public const SAMAN_STATUS_UNPAID = 'unpaid';

    protected $fillable = [
        'uuid',
        'public_uuid',
        'type',
        'code',
        'name',
        'brand',
        'model',
        'ownership_type',
        'owner_name',
        'status',
        'remark',
        'image',
    ];

    protected $appends = ['image_url'];

    /* =========================
     | Sub Types
     ========================= */

    public function vehicle(): HasOne
    {
        return $this->hasOne(InventoryVehicle::class);
    }

    /* =========================
     | Allocations
     ========================= */

    public function allocations()
    {
        return $this->hasMany(InventoryAllocation::class);
    }

    public function activeAllocation()
    {
        return $this->hasOne(InventoryAllocation::class)
            ->whereNull('to_date')
            ->latestOfMany();
    }

    /* =========================
     | Compliance / Legal
     ========================= */

    public function insurances(): HasMany
    {
        return $this->hasMany(InventoryInsurance::class);
    }

    public function latestInsurance(): HasOne
    {
        return $this->hasOne(InventoryInsurance::class)
            ->latest('expiry_date');
    }

    public function roadtaxes(): HasMany
    {
        return $this->hasMany(InventoryRoadtax::class);
    }

    public function latestRoadtax(): HasOne
    {
        return $this->hasOne(InventoryRoadtax::class)
            ->latest('expiry_date');
    }

    public function samans(): HasMany
    {
        return $this->hasMany(InventorySaman::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(InventoryService::class);
    }

    public function vehicleLogs(): HasMany
    {
        return $this->hasMany(InventoryVehicleLog::class);
    }

    public function latestVehicleMileageLog(): HasOne
    {
        return $this->hasOne(InventoryVehicleLog::class)
            ->whereNotNull('mileage')
            ->latest('trip_date');
    }

    public function unpaidSamans(): HasMany
    {
        return $this->hasMany(InventorySaman::class)
            ->where('status', self::SAMAN_STATUS_UNPAID);
    }

    public function scopeVehicle($query)
    {
        return $query->where('type', self::TYPE_VEHICLE);
    }

    /* =========================
     | Reminders
     ========================= */

    public function reminders(): HasMany
    {
        return $this->hasMany(InventoryReminder::class);
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
