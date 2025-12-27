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
        Schema::table('claim_items', function (Blueprint $table) {
            $table->string('receipt_no')->nullable()->after('claim_type');
            $table->string('title')->nullable()->after('receipt_no');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claim_items', function (Blueprint $table) {
            $table->dropColumn('receipt_no')->nullable();
            $table->dropColumn('title')->nullable();

        });
    }
};
