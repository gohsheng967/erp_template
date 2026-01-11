<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')
                ->constrained('petty_cash_wallets')
                ->cascadeOnDelete();

            // debit | credit | adjustment | cutoff
            $table->string('type', 20);

            // usage | topup | manual | cutoff
            $table->string('source', 30);

            // ONLY for usage
            $table->foreignId('claim_id')
                ->nullable()
                ->constrained('claims')
                ->nullOnDelete();

            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);

            $table->decimal('balance_after', 15, 2);

            $table->date('transaction_date');
            $table->unsignedBigInteger('created_by');

            $table->timestamps();

            $table->index(['wallet_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_transactions');
    }
};
