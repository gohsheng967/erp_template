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
        Schema::create('ar_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('invoice_no')->nullable()->unique();
            $table->string('title');

            $table->unsignedBigInteger('project_id')->nullable()->constrained();
            $table->unsignedBigInteger('customer_id')->nullable()->constrained();

            $table->decimal('total_amount', 15, 2)->default(0);

            $table->enum('status', [
                'draft',
                'issued',
                'approved',
                'received',
                'cancelled',
            ])->default('draft');

            // audit
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamp('issued_at')->nullable();

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('receipt_ref_no')->nullable();

            $table->text('remark')->nullable();

            $table->timestamps();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ar_invoices');
    }
};
