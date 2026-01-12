<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApInvoicePayment extends Model
{
    protected $fillable = [
        'uuid',
        'ap_invoice_id',
        'amount',
        'payment_date',
        'reference',
        'remarks',
        'created_by',
    ];

    public function invoice()
    {
        return $this->belongsTo(ApInvoice::class, 'ap_invoice_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
