<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClaimExpense;

class ClaimExpenseSeeder extends Seeder
{
    public function run(): void
    {
        ClaimExpense::create([
            'project_id' => 1,
            'title' => 'Team Lunch',
            'amount' => 320.50,
            'date' => '2025-01-20',
            'receipt_path' => 'receipts/lunch_receipt.jpg',
            'remarks' => 'Project kickoff lunch',
            'status' => 'submitted'
        ]);

        ClaimExpense::create([
            'project_id' => null, // general claim
            'title' => 'Office Supplies',
            'amount' => 120.00,
            'date' => '2025-01-22',
            'receipt_path' => 'receipts/supplies.jpg',
            'remarks' => 'Printer ink and paper',
            'status' => 'submitted'
        ]);
    }
}
