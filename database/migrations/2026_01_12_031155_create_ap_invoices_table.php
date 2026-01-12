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
        Schema::create('ap_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // === Foreign Keys (UNSIGNED) ===
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('created_by');

            $table->string('invoice_number'); // supplier invoice reference
            $table->date('invoice_date');
            $table->date('due_date');

            $table->decimal('invoice_amount', 15, 2);

            // 🔥 Partial payment support
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_amount', 15, 2);

            $table->enum('status', [
                'confirmed',
                'partially_paid',
                'paid',
                'cancelled'
            ])->default('confirmed');

            $table->text('remarks')->nullable();
            $table->timestamps();

            // === Constraints ===
            $table->foreign('purchase_order_id')
                ->references('id')->on('purchase_orders')
                ->cascadeOnDelete();

            $table->foreign('supplier_id')
                ->references('id')->on('suppliers');

            $table->foreign('created_by')
                ->references('id')->on('users');

            // Prevent duplicate supplier invoice
            $table->unique(['supplier_id', 'invoice_number']);

            // === Indexes (important for AP reports) ===
            $table->index('purchase_order_id');
            $table->index('status');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ap_invoices');
    }
};
