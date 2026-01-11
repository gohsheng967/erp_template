<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('purchase_order_item_id');

            $table->enum('type', [
                'IN',
                'OUT',
                'TRANSFER',
                'ADJUST',
            ]);

            $table->decimal('quantity', 12, 2);
            $table->decimal('balance_after', 12, 2)->nullable();

            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->string('remark')->nullable();

            $table->timestamps();

            $table->index(['warehouse_id', 'purchase_order_item_id']);
            $table->index(['reference_type', 'reference_id']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
