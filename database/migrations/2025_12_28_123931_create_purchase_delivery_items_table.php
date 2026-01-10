<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_delivery_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_delivery_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('purchase_order_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('quantity', 12, 2);

            // Destination tracking (warehouse / site / dept / project)
            $table->string('destination')->nullable();

            $table->string('remark')->nullable();

            $table->timestamps();

            $table->index([
                'purchase_delivery_id',
                'purchase_order_item_id'
            ], 'ppid');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_delivery_items');
    }
};
