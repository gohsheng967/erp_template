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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('code')->unique(); // PO-2025-0001

            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('supplier_id');

            $table->decimal('total_amount', 15, 2);

            $table->date('order_date')->nullable();

            $table->date('expected_delivery_date')->nullable();

            $table->string('status')->default('draft');
            // draft | issued | partially_delivered | completed | cancelled

            $table->text('terms')->nullable();
            $table->text('remark')->nullable();
            $table->datetime('approved_at')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
