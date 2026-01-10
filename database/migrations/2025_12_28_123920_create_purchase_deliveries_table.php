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
        Schema::create('purchase_deliveries', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('purchase_order_id')
                ->constrained()
                ->cascadeOnDelete();

            // Timeline content
            $table->string('title');
            $table->text('description')->nullable();

            // Delivery state
            $table->enum('status', [
                'transit',
                'warehouse',
                'returned',
            ])->index();

            // Progress type
            $table->enum('delivery_type', [
                'partial',
                'full',
            ])->default('partial')->index();

            // Logical date shown on timeline (NOT created_at)
            $table->date('delivery_date')->index();

            // Audit
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_deliveries');
    }
};
