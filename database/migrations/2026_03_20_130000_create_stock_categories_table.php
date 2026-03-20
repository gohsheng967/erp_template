<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        if (Schema::hasTable('inventory_movements') && Schema::hasColumn('inventory_movements', 'stock_category')) {
            $existing = DB::table('inventory_movements')
                ->whereNotNull('stock_category')
                ->where('stock_category', '!=', '')
                ->distinct()
                ->pluck('stock_category');

            if ($existing->isNotEmpty()) {
                DB::table('stock_categories')->insert(
                    $existing->map(function ($name) {
                        return [
                            'name' => $name,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    })->all()
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_categories');
    }
};
