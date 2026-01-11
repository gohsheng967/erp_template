<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('purchase_order_item_id');

            $table->decimal('quantity', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(['warehouse_id', 'purchase_order_item_id']);

            // Optional FK (add later if needed)
            // $table->foreign('warehouse_id')->references('id')->on('warehouses');
            // $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};
