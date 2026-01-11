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
        Schema::table('petty_cash_topups', function (Blueprint $table) {
            $table->string('payment_ref_no')->nullable()->after('amount');
        });

        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->string('payment_ref_no')->nullable()->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cash_topups', function (Blueprint $table) {
            $table->dropColumn('payment_ref_no');
        });

        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->dropColumn('payment_ref_no');
        });
    }
};
