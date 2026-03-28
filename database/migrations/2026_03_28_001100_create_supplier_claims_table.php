<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('claim_no')->nullable()->index();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('title');
            $table->string('status', 50)->default('submitted')->index();
            $table->decimal('claimed_amount', 14, 2)->default(0);
            $table->text('proforma_invoice_path')->nullable();
            $table->string('proforma_invoice_name')->nullable();
            $table->text('proof_attachment_path')->nullable();
            $table->string('proof_attachment_name')->nullable();
            $table->json('proof_attachments')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->json('remark_log')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_claims');
    }
};

