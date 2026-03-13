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
            $table->text('returned_remark')->nullable()->after('remark');
            $table->text('checked_remark')->nullable()->after('returned_remark');
            $table->text('verified_remark')->nullable()->after('checked_remark');
            $table->text('approved_remark')->nullable()->after('verified_remark');
            $table->text('rejected_remark')->nullable()->after('approved_remark');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn([
                'returned_remark',
                'checked_remark',
                'verified_remark',
                'approved_remark',
                'rejected_remark',
            ]);
        });
    }
};
