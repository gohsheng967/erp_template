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
        Schema::create('inventory_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();

            // MORPH
            $table->string('allocatable_type')->nullable();
            $table->unsignedBigInteger('allocatable_id')->nullable();

            // Manual / fallback
            $table->string('allocatable_name')->nullable();

            $table->string('location')->nullable();

            $table->date('from_date');
            $table->date('to_date')->nullable();

            $table->text('remark')->nullable();

            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();

            $table->index(['allocatable_type', 'allocatable_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_allocations');
    }
};
