<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ar_invoice_receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ar_invoice_id')
                ->constrained('ar_invoices')
                ->cascadeOnDelete();

            $table->decimal('amount', 15, 2);

            $table->string('reference')->nullable();

            // store file path if receipt image / PDF uploaded
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();

            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['ar_invoice_id']);
            $table->index(['received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_invoice_receipts');
    }
};
