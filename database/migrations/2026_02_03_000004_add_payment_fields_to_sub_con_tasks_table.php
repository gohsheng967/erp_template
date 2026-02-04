<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_con_tasks', function (Blueprint $table) {
            $table->string('payment_slip_no')->nullable()->after('payment_cert_no');
            $table->string('payment_ref_no')->nullable()->after('payment_slip_no');

            $table->text('verified_remark')->nullable()->after('verified_at');
            $table->text('verified_reject_remark')->nullable()->after('verified_remark');
            $table->text('justified_remark')->nullable()->after('justified_at');
            $table->text('justified_reject_remark')->nullable()->after('justified_remark');
        });
    }

    public function down(): void
    {
        Schema::table('sub_con_tasks', function (Blueprint $table) {
            $table->dropColumn([
                'payment_slip_no',
                'payment_ref_no',
                'verified_remark',
                'verified_reject_remark',
                'justified_remark',
                'justified_reject_remark',
            ]);
        });
    }
};
