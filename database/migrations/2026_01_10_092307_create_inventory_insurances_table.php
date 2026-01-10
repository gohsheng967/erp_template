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
        Schema::create('inventory_insurances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            $table->string('provider')->nullable();
            $table->string('policy_number')->nullable();

            $table->date('start_date')->nullable();
            $table->date('expiry_date');

            $table->decimal('coverage_amount', 15, 2)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();

            $table->timestamps();

            $table->index(['inventory_item_id', 'expiry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_insurances');
    }
};
