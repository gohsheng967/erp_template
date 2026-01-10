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
        Schema::create('inventory_reminders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            $table->enum('type', ['insurance', 'roadtax', 'saman', 'maintenance']);
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->date('trigger_date');

            $table->enum('status', ['pending', 'sent', 'dismissed'])->default('pending');
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['type', 'trigger_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_samen');
    }
};
