<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sub_con_claims') || Schema::hasColumn('sub_con_claims', 'purchase_order_ids')) {
            return;
        }

        Schema::table('sub_con_claims', function (Blueprint $table) {
            $table->json('purchase_order_ids')->nullable()->after('purchase_order_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sub_con_claims') || !Schema::hasColumn('sub_con_claims', 'purchase_order_ids')) {
            return;
        }

        Schema::table('sub_con_claims', function (Blueprint $table) {
            $table->dropColumn('purchase_order_ids');
        });
    }
};
