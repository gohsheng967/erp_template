<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PettyCashTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'type',
        'source',
        'source_type',
        'source_id',
        'debit_amount',
        'credit_amount',
        'balance_after',
        'transaction_date',
        'created_by',
        'code',
        'source_ref_no',
        'display_type'
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function wallet()
    {
        return $this->belongsTo(PettyCashWallet::class);
    }

    public function claim()
    {
        return $this->belongsTo(Claim::class);
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

    public function sourceable()
    {
        return $this->morphTo(
            name: 'sourceable',
            type: 'source_type',
            id: 'source_id'
        );
    }



}
