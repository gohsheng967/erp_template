<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('petty_cash_transactions', 'source_type')) {
                $table->string('source_type')->nullable()->after('source');
            }

            if (! Schema::hasColumn('petty_cash_transactions', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
        });

        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $sm = Schema::getConnection()->getSchemaBuilder();
            if (
                $sm->hasColumn('petty_cash_transactions', 'source_type') &&
                $sm->hasColumn('petty_cash_transactions', 'source_id')
            ) {
                try {
                    $table->index(['source_type', 'source_id'], 'pct_source_type_source_id_index');
                } catch (\Throwable $e) {
                    // Ignore if index already exists on environments with partial schema drift.
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $sm = Schema::getConnection()->getSchemaBuilder();
            if ($sm->hasColumn('petty_cash_transactions', 'source_type') && $sm->hasColumn('petty_cash_transactions', 'source_id')) {
                try {
                    $table->dropIndex('pct_source_type_source_id_index');
                } catch (\Throwable $e) {
                    // Ignore if index does not exist.
                }
            }
        });

        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('petty_cash_transactions', 'source_id')) {
                $table->dropColumn('source_id');
            }

            if (Schema::hasColumn('petty_cash_transactions', 'source_type')) {
                $table->dropColumn('source_type');
            }
        });
    }
};
