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
        Schema::create('inventory_saman', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            $table->string('offense_name'); // speeding, illegal parking, red light, etc
            $table->string('source')->nullable(); // JPJ, PDRM, AES
            $table->string('reference_no')->nullable();

            $table->date('offence_date')->nullable();
            $table->decimal('amount', 10, 2);

            $table->enum('status', ['unpaid', 'paid', 'disputed'])->default('unpaid');
            $table->timestamp('paid_at')->nullable();

            $table->unsignedBigInteger('document_id')->nullable();

            $table->timestamps();

            $table->index(['inventory_item_id', 'status']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_reminders');
    }
};
