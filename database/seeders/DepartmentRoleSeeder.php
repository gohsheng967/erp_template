<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentRoleSeeder extends Seeder
{
    public function run(): void
    {
        $fixedRoles = Role::query()
            ->whereIn('name', Role::fixedRoleNames())
            ->get(['id', 'name']);

        $fixedRoleIds = $fixedRoles->pluck('id')->values()->all();
        $departmentRoleIds = $fixedRoles
            ->whereIn('name', Role::departmentRoleNames())
            ->pluck('id')
            ->values()
            ->all();

        foreach (
            Department::query()
                ->whereIn('name', Department::fixedDepartmentNames())
                ->get(['id']) as $department
        ) {
            // Enforce department-assignable roles only (GM is not department-linked).
            $department->roles()->sync($departmentRoleIds);
        }

        // Normalize existing users assigned to retired roles -> Staff.
        $staffRoleId = (int) $fixedRoles->firstWhere('name', 'Staff')?->id;
        if ($staffRoleId <= 0) {
            return;
        }

        $legacyCeoRoleId = (int) Role::query()->where('name', 'CEO')->value('id');
        if ($legacyCeoRoleId > 0) {
            DB::table('pivot_user_departments')
                ->where('role_id', $legacyCeoRoleId)
                ->update([
                    'role_id' => $staffRoleId,
                    'updated_at' => now(),
                ]);
        }

        DB::table('pivot_user_departments')
            ->whereNotIn('role_id', $fixedRoleIds)
            ->update([
                'role_id' => $staffRoleId,
                'updated_at' => now(),
            ]);

        Role::query()->where('name', 'CEO')->delete();
    }
}
