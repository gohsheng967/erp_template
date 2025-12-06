<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProjectActivityLog;

class ProjectActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        ProjectActivityLog::create([
            'project_id' => 1,
            'user_id' => 1,
            'action' => 'project_created',
            'data' => ['message' => 'Project ERP Implementation created'],
        ]);

        ProjectActivityLog::create([
            'project_id' => 1,
            'user_id' => 2,
            'action' => 'document_uploaded',
            'data' => ['file' => 'contract_v1.pdf'],
        ]);
    }
}
