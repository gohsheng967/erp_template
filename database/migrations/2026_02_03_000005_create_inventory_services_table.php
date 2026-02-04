<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();
            $table->date('service_date');
            $table->text('item_parts');
            $table->decimal('cost', 12, 2)->default(0);
            $table->string('vendor')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_services');
    }
};
