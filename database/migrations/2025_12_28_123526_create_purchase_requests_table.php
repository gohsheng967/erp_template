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
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->nullable(); // PR-2025-0001
            $table->string('title');
            $table->text('purpose')->nullable();

            $table->foreignId('department_id')->nullable()->constrained();
            $table->foreignId('requested_by')->constrained('users');

            $table->string('status')->default('draft');
            // draft | submitted | approved | cancelled | completed

            $table->foreignId('approved_quotation_id')->nullable();

            $table->text('requester_remark')->nullable();
            $table->text('reviewer_remark')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
