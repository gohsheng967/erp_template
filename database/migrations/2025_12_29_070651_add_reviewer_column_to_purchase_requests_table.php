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
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->foreignId('reviewer')->nullable()->after('reviewer_remark');
            $table->datetime('submitted_at')->nullable()->after('reviewer');
            $table->date('required_date')->nullable()->after('reviewer');
            $table->date('approved_at')->nullable()->after('required_date');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('reviewer');
            $table->dropColumn('submitted_at');
            $table->dropColumn('required_date');
            $table->dropColumn('approved_at');
        });
    }
};
