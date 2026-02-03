<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('claim_types')->insert([
            [
                'code' => 'travel',
                'name' => 'Travel',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'food_beverage',
                'name' => 'Food & Beverage',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'optical',
                'name' => 'Optical',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'medical',
                'name' => 'Medical',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'communication',
                'name' => 'Communication',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'others',
                'name' => 'Others',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_types');
    }
};
