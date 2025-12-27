<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Parent claim (header)
            $table->foreignId('claim_id')
                ->constrained()
                ->cascadeOnDelete();

            // Item details
            $table->string('claim_type')->nullable();              // from config/claim.php
            $table->string('description')->nullable(); // optional notes

            // Amount & receipt
            $table->decimal('amount', 12, 2);
            $table->date('receipt_date')->nullable();

            $table->timestamps();

            // Optional but recommended indexes
            $table->index('claim_type');
            $table->index('receipt_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_items');
    }
};
