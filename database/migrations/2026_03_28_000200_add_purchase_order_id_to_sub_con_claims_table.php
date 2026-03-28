<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sub_con_claims') || Schema::hasColumn('sub_con_claims', 'purchase_order_id')) {
            return;
        }

        Schema::table('sub_con_claims', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')
                ->nullable()
                ->after('sub_con_id')
                ->constrained('purchase_orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sub_con_claims') || !Schema::hasColumn('sub_con_claims', 'purchase_order_id')) {
            return;
        }

        Schema::table('sub_con_claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
        });
    }
};
