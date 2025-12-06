<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        Project::create([
            'code' => 'PRJ-001',
            'name' => 'ERP Implementation Project',
            'client_id' => 1,
            'start_date' => '2025-01-10',
            'end_date' => '2025-05-30',
            'budget' => 250000,
            'department_id' => 1,
            'manager_id' => 1,
            'status' => 'active',
            'description' => 'Development of full ERP system including HR, Project, Supplier, Finance modules.'
        ]);

        Project::create([
            'code' => 'PRJ-002',
            'name' => 'Website Revamp Project',
            'client_id' => 2,
            'start_date' => '2025-02-01',
            'end_date' => '2025-04-01',
            'budget' => 80000,
            'department_id' => 2,
            'manager_id' => 2,
            'status' => 'active',
            'description' => 'Full redesign and development of company website.'
        ]);
    }
}
