<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        Client::create([
            'name' => 'ABC Corporation',
            'email' => 'contact@abc.com',
            'phone' => '012-3456789',
            'company_name' => 'ABC Corporation',
            'address' => '123 Jalan ABC, Kuala Lumpur'
        ]);

        Client::create([
            'name' => 'XYZ Holdings',
            'email' => 'admin@xyz.com',
            'phone' => '017-9988776',
            'company_name' => 'XYZ Holdings Sdn Bhd',
            'address' => '55 Jalan XYZ, Petaling Jaya'
        ]);
    }
}
