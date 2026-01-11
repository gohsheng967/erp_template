<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PettyCashStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'month',
        'opening_balance',
        'closing_balance',
        'remark',
        'attachment_id',
        'created_by',
        'locked_at',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'locked_at' => 'datetime',
    ];

    public function wallet()
    {
        return $this->belongsTo(PettyCashWallet::class);
    }
}
