<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProjectModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProjectSeeder::class,
            ProjectDocumentSeeder::class,
            ProjectMilestoneSeeder::class,
            ProjectTaskSeeder::class,
            ClaimExpenseSeeder::class,
            PurchaseRequestSeeder::class,
            ProjectActivityLogSeeder::class,
        ]);
    }
}
