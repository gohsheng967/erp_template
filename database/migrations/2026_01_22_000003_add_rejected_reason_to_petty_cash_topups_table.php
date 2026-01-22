<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('petty_cash_topups', function (Blueprint $table) {
            $table->string('rejected_reason')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_topups', function (Blueprint $table) {
            $table->dropColumn('rejected_reason');
        });
    }
};
