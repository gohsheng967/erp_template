<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'IT', 'description' => 'System Administrator'],
            ['name' => 'Director', 'description' => 'Top Level Management'],
            ['name' => 'Department Head', 'description' => 'Head of Department'],
            ['name' => 'Staff', 'description' => 'General Staff'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}
