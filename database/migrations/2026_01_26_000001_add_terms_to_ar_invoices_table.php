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
        Schema::table('ar_invoices', function (Blueprint $table) {
            $table->unsignedInteger('payment_term_days')->nullable()->after('total_amount');
            $table->date('due_date')->nullable()->after('payment_term_days');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ar_invoices', function (Blueprint $table) {
            $table->dropIndex(['due_date']);
            $table->dropColumn('due_date');
            $table->dropColumn('payment_term_days');
        });
    }
};
