<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class PettyCashWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'context_type',
        'context_id',
        'currency_id',
        'opening_balance',
        'current_balance',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    /* =========================
       Relationships
    ========================= */

    public function transactions()
    {
        return $this->hasMany(PettyCashTransaction::class, 'wallet_id');
    }

    public function statements()
    {
        return $this->hasMany(PettyCashStatement::class, 'wallet_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'context_id');
    }

    /* =========================
       Context helpers
    ========================= */

    public function isOffice(): bool
    {
        return $this->context_type === 'office';
    }

    public function isProject(): bool
    {
        return $this->context_type === 'project';
    }

    /* =========================
       Balance helpers (read-only)
    ========================= */

    public function canDebit(float $amount): bool
    {
        return bccomp((string) $this->current_balance, (string) $amount, 2) >= 0;
    }
}
