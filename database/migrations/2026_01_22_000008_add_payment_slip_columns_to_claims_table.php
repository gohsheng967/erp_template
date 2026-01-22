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
        Schema::table('claims', function (Blueprint $table) {
            $table->string('payment_slip_no')->nullable()->after('payment_ref_no');
            $table->foreignId('company_bank_account_id')
                ->nullable()
                ->after('payment_slip_no')
                ->constrained('company_bank_accounts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_bank_account_id');
            $table->dropColumn('payment_slip_no');
        });
    }
};
