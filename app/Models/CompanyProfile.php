<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $fillable = [
        'company_name',
        'company_reg_no',
        'address',
        'office_number',
        'logo',
    ];
}
