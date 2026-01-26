<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class ArInvoice extends Model
{
    use HasFactory;

    protected $table = 'ar_invoices';

    protected $fillable = [
        'uuid',
        'invoice_no',
        'title',

        'project_id',
        'customer_id',

        'total_amount',
        'payment_term_days',
        'due_date',
        'status',

        'issued_by',
        'issued_at',

        'approved_by',
        'approved_at',

        'received_by',
        'received_at',
        'receipt_ref_no',

        'remark',
    ];

    protected $casts = [
        'issued_at'   => 'datetime',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'total_amount'=> 'decimal:2',
        'payment_term_days' => 'integer',
        'due_date'   => 'date',
    ];

    /* ============================================================
     * BOOT
     * ============================================================
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /* ============================================================
     * RELATIONSHIPS
     * ============================================================
     */

    // Line items
    public function items()
    {
        return $this->hasMany(ArInvoiceItem::class);
    }

    // Project (optional)
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // Customer (AR specific)
    public function customer()
    {
        return $this->belongsTo(Client::class);
    }

    // User who created / issued
    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    // Approver
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // User who received payment
    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // Header-level attachments (invoice pdf, payment voucher)
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /* ============================================================
     * SCOPES
     * ============================================================
     */

    public function scopeDraft($q)
    {
        return $q->where('status', 'draft');
    }

    public function scopeIssued($q)
    {
        return $q->where('status', 'issued');
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }

    public function scopeReceived($q)
    {
        return $q->where('status', 'received');
    }

    /* ============================================================
     * HELPERS
     * ============================================================
     */

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isApprovable(): bool
    {
        return $this->status === 'issued';
    }

    public function isReceivable(): bool
    {
        return $this->status === 'approved';
    }

    public function receipts()
    {
        return $this->hasMany(ArInvoiceReceipt::class);
    }

    public function getTotalReceivedAttribute(): float
    {
        return (float) $this->receipts()->sum('amount');
    }

    public function getOutstandingAmountAttribute(): float
    {
        return max(
            (float) $this->total_amount - $this->total_received,
            0
        );
    }

    public function isFullyPaid(): bool
    {
        return $this->total_received >= $this->total_amount;
    }

}
