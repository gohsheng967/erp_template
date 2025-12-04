<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Role;

class DepartmentRoleSeeder extends Seeder
{
    public function run()
    {
        // Example structure — adjust to your ERP needs
        $map = [
            'HR' => ['Manager', 'Recruiter', 'Trainer'],
            'Finance' => ['Manager', 'Accountant', 'Auditor'],
            'Superadmin' => ['Superadmin'],
            'Operations' => ['Supervisor', 'Staff'],
        ];

        foreach ($map as $departmentName => $roles) {

            $department = Department::firstOrCreate(['name' => $departmentName]);

            foreach ($roles as $roleName) {

                $role = Role::firstOrCreate(['name' => $roleName]);

                $department->roles()->syncWithoutDetaching($role->id);
            }
        }
    }
}
