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
        'internal_note',
        'login_identity_no',
        'login_email',
        'login_password',
        'login_status',
        'login_must_change_password',
    ];

    protected $hidden = [
        'login_password',
    ];

    protected $casts = [
        'login_must_change_password' => 'boolean',
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
        return $this->hasMany(PurchaseOrder::class);
    }

    public function invoices()
    {
        return $this->hasMany(ApInvoice::class);
    }

    public function claims()
    {
        return $this->hasMany(SupplierClaim::class, 'supplier_id');
    }
}
