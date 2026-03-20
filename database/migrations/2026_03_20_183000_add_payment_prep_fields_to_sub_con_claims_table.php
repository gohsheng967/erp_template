<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_con_claims', function (Blueprint $table) {
            $table->string('payment_slip_no')->nullable()->after('approved_amount');
            $table->timestamp('payment_slip_prepared_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('sub_con_claims', function (Blueprint $table) {
            $table->dropColumn([
                'payment_slip_no',
                'payment_slip_prepared_at',
            ]);
        });
    }
};

