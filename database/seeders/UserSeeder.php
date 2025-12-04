<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use App\Models\Permission;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::pluck('id');
        $permissions = Permission::pluck('id');

        // Fetch roles
        $itRole       = Role::where('name', 'IT')->first();
        $directorRole = Role::where('name', 'Director')->first();

        /*
        |--------------------------------------------------------------------------
        | USER 1 — IT ADMIN
        |--------------------------------------------------------------------------
        */
        $itUser = User::firstOrCreate(
            ['identity_no' => 'IT001'],
            [
                'name'     => 'IT Administrator',
                'email'    => 'it@example.com',
                'password' => bcrypt('password123'),
                'status'   => 1,
            ]
        );

        $itRole->permissions()->sync($permissions);

        foreach ($departments as $deptId) {
            \DB::table('pivot_user_departments')->updateOrInsert([
                'user_id'       => $itUser->id,
                'department_id' => $deptId,
                'role_id'       => $itRole->id,
            ]);
        }

        $directorUser = User::firstOrCreate(
            ['identity_no' => 'DIR001'],
            [
                'name'     => 'Director',
                'email'    => 'director@example.com',
                'password' => bcrypt('password123'),
                'status'   => 1,
            ]
        );

        $directorRole->permissions()->sync($permissions);

        foreach ($departments as $deptId) {
            \DB::table('pivot_user_departments')->updateOrInsert([
                'user_id'       => $directorUser->id,
                'department_id' => $deptId,
                'role_id'       => $directorRole->id,
            ]);
        }
    }
}
