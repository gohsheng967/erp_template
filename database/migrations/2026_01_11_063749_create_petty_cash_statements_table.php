<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('petty_cash_statements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')
                ->constrained('petty_cash_wallets')
                ->cascadeOnDelete();

            $table->string('month', 7); // YYYY-MM

            $table->decimal('opening_balance', 15, 2);
            $table->decimal('closing_balance', 15, 2);

            $table->text('remark')->nullable();

            $table->unsignedBigInteger('attachment_id')->nullable();
            $table->unsignedBigInteger('created_by');

            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['wallet_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_statements');
    }
};
