<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Branch;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $activeBranchId = Branch::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        if (!$activeBranchId) {
            $activeBranchId = Branch::query()
                ->orderBy('id')
                ->value('id');
        }

        if (!$activeBranchId) {
            throw new RuntimeException('Please create at least one branch before seeding users.');
        }

        $superUser = User::firstOrCreate(
            ['identity_no' => 'SUPER001'],
            [
                'name'     => 'System Superadmin',
                'email'    => 'superadmin@example.com',
                'password' => bcrypt('password123'),
                'status'   => 1,
                'is_superadmin' => true,
                'active_branch_id' => $activeBranchId,
            ]
        );

        if ($activeBranchId) {
            DB::table('pivot_user_branches')->updateOrInsert([
                'user_id' => $superUser->id,
                'branch_id' => $activeBranchId,
            ], [
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }
}
