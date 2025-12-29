<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'company_name',
        'registration_no',
        'contact_person',
        'contact_phone',
        'email',
        'address',
        'status',
        'internal_note'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function quotations()
    {
        return $this->hasMany(PurchaseQuotation::class);
    }

    public function purchaseOrders()
    {
        return $this->hasManyThrough(
            PurchaseOrder::class,
            PurchaseQuotation::class,
            'supplier_id',             
            'purchase_quotation_id',  
            'id',                       
            'id'                        
        );
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
