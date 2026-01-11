<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('petty_cash_topups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('wallet_id')
                ->constrained('petty_cash_wallets')
                ->cascadeOnDelete();

            $table->decimal('amount', 15, 2);
            $table->string('reason')->nullable();

            // requested | approved | paid
            $table->string('status', 20)->default('requested');

            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_topups');
    }
};
