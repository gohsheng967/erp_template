<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_con_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('claim_no')->nullable()->index();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sub_con_id')->constrained('sub_cons')->cascadeOnDelete();
            $table->string('title');
            $table->string('status', 50)->default('submitted')->index();
            $table->decimal('claimed_amount', 14, 2)->default(0);
            $table->decimal('project_verified_amount', 14, 2)->nullable();
            $table->decimal('contra_verified_amount', 14, 2)->nullable();
            $table->decimal('approved_amount', 14, 2)->nullable();
            $table->unsignedInteger('appeal_round')->default(0);
            $table->text('proforma_invoice_path')->nullable();
            $table->string('proforma_invoice_name')->nullable();
            $table->text('real_invoice_path')->nullable();
            $table->string('real_invoice_name')->nullable();
            $table->string('real_invoice_no')->nullable();
            $table->date('real_invoice_date')->nullable();
            $table->decimal('real_invoice_amount', 14, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('project_verified_at')->nullable();
            $table->timestamp('contra_verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('subcon_decided_at')->nullable();
            $table->timestamp('real_invoice_uploaded_at')->nullable();
            $table->json('remark_log')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_con_claims');
    }
};

