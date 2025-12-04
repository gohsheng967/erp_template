<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Dashboard
            'dashboard.view',

            // User Management
            'user.view',
            'user.create',
            'user.edit',
            'user.delete',

            // Department
            'department.view',
            'department.manage',

            // Roles
            'role.manage',

            // Projects
            'project.view',
            'project.manage',

            // Finance
            'finance.view',
            'finance.manage',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // IT Role gets all permissions
        $it = Role::where('name', 'IT')->first();
        $it->permissions()->sync(Permission::pluck('id'));
    }
}
