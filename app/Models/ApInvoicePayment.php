<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApInvoicePayment extends Model
{
    protected $fillable = [
        'uuid',
        'ap_invoice_id',
        'amount',
        'payment_date',
        'reference',
        'remarks',
        'payment_slip_no',
        'company_bank_account_id',
        'less_retention',
        'less_recoupment',
        'less_material_ob',
        'less_paid_previously',
        'payment_slip_remark',
        'created_by',
    ];

    public function invoice()
    {
        return $this->belongsTo(ApInvoice::class, 'ap_invoice_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function companyBankAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id');
    }
}
