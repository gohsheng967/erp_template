<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_request_items') && !Schema::hasColumn('purchase_request_items', 'parent_id')) {
            Schema::table('purchase_request_items', function (Blueprint $table) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('purchase_request_id')
                    ->constrained('purchase_request_items')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('purchase_order_items') && !Schema::hasColumn('purchase_order_items', 'purchase_request_item_id')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('purchase_request_item_id')
                    ->nullable()
                    ->after('purchase_order_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_order_items') && Schema::hasColumn('purchase_order_items', 'purchase_request_item_id')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropColumn('purchase_request_item_id');
            });
        }

        if (Schema::hasTable('purchase_request_items') && Schema::hasColumn('purchase_request_items', 'parent_id')) {
            Schema::table('purchase_request_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('parent_id');
            });
        }
    }
};

