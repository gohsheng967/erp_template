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
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('claim_no')->nullable(); // CLM-2025-000123
            $table->foreignId('user_id')->constrained(); // creator
            $table->foreignId('project_id')->nullable()->constrained();

            $table->string('title');
            $table->text('description')->nullable();

            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('MYR');

            $table->enum('status', [
                'draft',
                'rejected',
                'submitted',
                'approved',
                'paid'
            ])->default('submitted');

            // approval
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            // payment
            $table->foreignId('paid_by')->nullable()->constrained('users');
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
