<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_topups', function (Blueprint $table) {
            $table->unsignedBigInteger('verified_by')->nullable()->after('requested_by');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_topups', function (Blueprint $table) {
            $table->dropColumn(['verified_by', 'verified_at']);
        });
    }
};

