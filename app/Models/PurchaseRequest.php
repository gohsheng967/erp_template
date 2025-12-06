<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_requests';

    protected $fillable = [
        'project_id',
        'item',
        'quantity',
        'est_price',
        'reason',
        'attachment_path',
        'status' // submitted
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
