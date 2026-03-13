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
            $table->json('remark_log')->nullable()->after('remark');
        });

        Schema::table('claims', function (Blueprint $table) {
            $columns = [
                'returned_remark',
                'checked_remark',
                'verified_remark',
                'approved_remark',
                'rejected_remark',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('claims', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            if (!Schema::hasColumn('claims', 'returned_remark')) {
                $table->text('returned_remark')->nullable()->after('remark');
            }
            if (!Schema::hasColumn('claims', 'checked_remark')) {
                $table->text('checked_remark')->nullable()->after('returned_remark');
            }
            if (!Schema::hasColumn('claims', 'verified_remark')) {
                $table->text('verified_remark')->nullable()->after('checked_remark');
            }
            if (!Schema::hasColumn('claims', 'approved_remark')) {
                $table->text('approved_remark')->nullable()->after('verified_remark');
            }
            if (!Schema::hasColumn('claims', 'rejected_remark')) {
                $table->text('rejected_remark')->nullable()->after('approved_remark');
            }
        });

        Schema::table('claims', function (Blueprint $table) {
            if (Schema::hasColumn('claims', 'remark_log')) {
                $table->dropColumn('remark_log');
            }
        });
    }
};
