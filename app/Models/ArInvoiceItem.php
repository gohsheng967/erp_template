<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ArInvoiceItem extends Model
{
    use HasFactory;

    protected $table = 'ar_invoice_items';

    protected $fillable = [
        'ar_invoice_id',

        'title',
        'description',

        'quantity',
        'unit_price',
        'amount',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_price' => 'decimal:2',
        'amount'     => 'decimal:2',
    ];

    /* ============================================================
     * RELATIONSHIPS
     * ============================================================
     */

    public function invoice()
    {
        return $this->belongsTo(ArInvoice::class, 'ar_invoice_id');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /* ============================================================
     * MODEL EVENTS
     * ============================================================
     */

    protected static function booted()
    {
        static::saving(function ($item) {
            // Safety net: auto calculate amount if missing
            if (
                ($item->quantity !== null && $item->unit_price !== null)
                && ($item->amount === null)
            ) {
                $item->amount = bcmul(
                    (string) $item->quantity,
                    (string) $item->unit_price,
                    2
                );
            }
        });
    }
}
