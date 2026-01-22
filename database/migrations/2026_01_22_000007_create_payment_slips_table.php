<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_slips', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slip_no')->unique();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->foreignId('company_bank_account_id')
                ->nullable()
                ->constrained('company_bank_accounts')
                ->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->decimal('less_retention', 15, 2)->nullable();
            $table->decimal('less_recoupment', 15, 2)->nullable();
            $table->decimal('less_material_ob', 15, 2)->nullable();
            $table->decimal('less_paid_previously', 15, 2)->nullable();
            $table->string('payment_slip_remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_slips');
    }
};
