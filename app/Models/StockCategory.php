<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCategory extends Model
{
    protected $fillable = [
        'name',
    ];

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class, 'stock_category', 'name');
    }
}
