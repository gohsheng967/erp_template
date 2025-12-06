<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClaimExpense extends Model
{
    use SoftDeletes;

    protected $table = 'claim_expenses';

    protected $fillable = [
        'project_id',
        'title',
        'amount',
        'date',
        'receipt_path',
        'remarks',
        'status' // submitted
    ];

    protected $casts = [
        'date' => 'date'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
