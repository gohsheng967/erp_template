<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PurchaseRequest;

class PurchaseRequestSeeder extends Seeder
{
    public function run(): void
    {
        PurchaseRequest::create([
            'project_id' => 1,
            'item' => 'Laptop Lenovo ThinkPad',
            'quantity' => 2,
            'est_price' => 5200.00,
            'reason' => 'Developers require upgraded machines',
            'attachment_path' => null,
            'status' => 'submitted'
        ]);

        PurchaseRequest::create([
            'project_id' => null,
            'item' => 'Office Chair',
            'quantity' => 5,
            'est_price' => 300.00,
            'reason' => 'Replacement chairs for office',
            'attachment_path' => null,
            'status' => 'submitted'
        ]);
    }
}
