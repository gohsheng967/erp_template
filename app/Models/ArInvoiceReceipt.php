<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ArInvoiceReceipt extends Model
{
    use HasFactory;

    protected $table = 'ar_invoice_receipts';

    protected $fillable = [
        'ar_invoice_id',
        'amount',
        'reference',
        'attachment_path',
        'attachment_name',
        'received_at',
        'received_by',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'received_at' => 'datetime',
    ];

    /* =========================
       RELATIONSHIPS
    ========================== */

    public function invoice()
    {
        return $this->belongsTo(ArInvoice::class, 'ar_invoice_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
