<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyBankAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'bank_name',
        'account_name',
        'account_no',
        'status',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
