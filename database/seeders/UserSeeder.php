<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $departments  = Department::all();
        $permissions  = Permission::pluck('id');

        /*
        |--------------------------------------------------------------------------
        | SUPERADMIN USER
        |--------------------------------------------------------------------------
        */
        $superDept = Department::firstOrCreate(['name' => 'Superadmin']);
        $superRole = Role::firstOrCreate(['name' => 'Superadmin']);

        // Attach all permissions to Superadmin role
        $superRole->permissions()->sync($permissions);

        // Link role to department
        $superDept->roles()->syncWithoutDetaching($superRole->id);

        // Create Superadmin user
        $superUser = User::firstOrCreate(
            ['identity_no' => 'SUPER001'],
            [
                'name'     => 'System Superadmin',
                'email'    => 'superadmin@example.com',
                'password' => bcrypt('password123'),
                'status'   => 1,
            ]
        );

        // Assign Superadmin department-role to Superadmin user
        DB::table('pivot_user_departments')->updateOrInsert([
            'user_id'       => $superUser->id,
            'department_id' => $superDept->id,
            'role_id'       => $superRole->id,
        ]);
    }
}
