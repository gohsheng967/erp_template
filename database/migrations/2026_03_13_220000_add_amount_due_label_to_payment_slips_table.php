<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_slips', function (Blueprint $table) {
            $table->string('amount_due_label')->nullable()->after('less_paid_previously_label');
        });
    }

    public function down(): void
    {
        Schema::table('payment_slips', function (Blueprint $table) {
            $table->dropColumn('amount_due_label');
        });
    }
};

