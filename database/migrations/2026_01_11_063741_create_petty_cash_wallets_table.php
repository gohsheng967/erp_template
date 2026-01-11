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
       Schema::create('petty_cash_wallets', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            /*
             * Context:
             * - office  → context_id = null
             * - project → context_id = projects.id
             */
            $table->string('context_type', 20); // office | project
            $table->unsignedBigInteger('context_id')->nullable();

            $table->unsignedBigInteger('currency_id')->nullable();

            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['context_type', 'context_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_wallets');
    }
};
