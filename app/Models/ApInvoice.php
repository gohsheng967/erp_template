<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApInvoice extends Model
{
    use HasFactory;

    protected $table = 'ap_invoices';

    protected $fillable = [
        'uuid',
        'purchase_order_id',
        'supplier_id',
        'branch_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'invoice_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date'     => 'date',
        'invoice_amount' => 'decimal:2',
        'paid_amount'    => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    /* =========================
       Relationships
    ========================= */

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function payments()
    {
        return $this->hasMany(ApInvoicePayment::class, 'ap_invoice_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* =========================
       Business Logic
    ========================= */

    /**
     * Recalculate paid & balance amount
     * and auto-update status
     */
    public function recalcBalance(): void
    {
        $this->paid_amount = $this->payments()->sum('amount');
        $this->balance_amount = $this->invoice_amount - $this->paid_amount;

        if ($this->balance_amount <= 0) {
            $this->status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partially_paid';
        } else {
            $this->status = 'confirmed';
        }

        $this->save();
    }

    /**
     * Scope: outstanding invoices
     */
    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['confirmed', 'partially_paid']);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function paymentSlips()
    {
        return $this->morphMany(PaymentSlip::class, 'source');
    }
}
