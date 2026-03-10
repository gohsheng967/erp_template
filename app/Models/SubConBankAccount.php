<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubConBankAccount extends Model
{
    protected $fillable = [
        'sub_con_id',
        'bank_name',
        'account_name',
        'account_no',
    ];

    public function subCon()
    {
        return $this->belongsTo(SubCon::class, 'sub_con_id');
    }
}

