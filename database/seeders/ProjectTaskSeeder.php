<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProjectTask;

class ProjectTaskSeeder extends Seeder
{
    public function run(): void
    {
        ProjectTask::create([
            'milestone_id' => 2,
            'title' => 'User Module API',
            'assigned_to' => 1,
            'deadline' => '2025-02-01',
            'status' => 'in_progress'
        ]);

        ProjectTask::create([
            'milestone_id' => 2,
            'title' => 'Project Module API',
            'assigned_to' => 2,
            'deadline' => '2025-02-15',
            'status' => 'pending'
        ]);
    }
}
