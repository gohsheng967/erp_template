<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProjectMilestone;

class ProjectMilestoneSeeder extends Seeder
{
    public function run(): void
    {
        // Claim-based milestone
        ProjectMilestone::create([
            'project_id' => 1,
            'type' => 'claim',
            'title' => 'Phase 1 Completion Claim',
            'amount' => 50000,
            'due_date' => '2025-02-15',
            'status' => 'pending'
        ]);

        // Task-based milestone
        ProjectMilestone::create([
            'project_id' => 1,
            'type' => 'task',
            'title' => 'Backend API Development',
            'due_date' => '2025-03-10',
            'status' => 'in_progress'
        ]);

        // Timeline-based milestone
        ProjectMilestone::create([
            'project_id' => 1,
            'type' => 'timeline',
            'title' => 'UI/UX Design',
            'start_date' => '2025-01-10',
            'end_date' => '2025-02-10',
            'status' => 'pending'
        ]);
    }
}
