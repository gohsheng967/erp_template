<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ap_invoice_payments', function (Blueprint $table) {
            $table->string('payment_slip_no')->nullable()->after('remarks');
            $table->foreignId('company_bank_account_id')
                ->nullable()
                ->constrained('company_bank_accounts')
                ->nullOnDelete()
                ->after('payment_slip_no');
            $table->decimal('less_retention', 15, 2)->nullable()->after('company_bank_account_id');
            $table->decimal('less_recoupment', 15, 2)->nullable()->after('less_retention');
            $table->decimal('less_material_ob', 15, 2)->nullable()->after('less_recoupment');
            $table->decimal('less_paid_previously', 15, 2)->nullable()->after('less_material_ob');
            $table->string('payment_slip_remark')->nullable()->after('less_paid_previously');
        });
    }

    public function down(): void
    {
        Schema::table('ap_invoice_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_bank_account_id');
            $table->dropColumn([
                'payment_slip_no',
                'less_retention',
                'less_recoupment',
                'less_material_ob',
                'less_paid_previously',
                'payment_slip_remark',
            ]);
        });
    }
};
