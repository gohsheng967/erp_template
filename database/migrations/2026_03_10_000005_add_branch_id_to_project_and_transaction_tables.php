<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('department_id')
                ->constrained('branches')
                ->nullOnDelete();
            $table->index(['branch_id', 'status']);
        });

        Schema::table('claims', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('project_id')
                ->constrained('branches')
                ->nullOnDelete();
            $table->index(['branch_id', 'status']);
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('project_id')
                ->constrained('branches')
                ->nullOnDelete();
            $table->index(['branch_id', 'status']);
        });

        Schema::table('ar_invoices', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('project_id')
                ->constrained('branches')
                ->nullOnDelete();
            $table->index(['branch_id', 'status']);
        });

        Schema::table('ap_invoices', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('branches')
                ->nullOnDelete();
            $table->index(['branch_id', 'status']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('purchase_request_id')
                ->constrained('branches')
                ->nullOnDelete();
            $table->index(['branch_id', 'status']);
        });

        $defaultBranchId = DB::table('branches')
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        if (!$defaultBranchId) {
            $defaultBranchId = DB::table('branches')->orderBy('id')->value('id');
        }

        if ($defaultBranchId) {
            DB::table('projects')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
            DB::table('claims')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
            DB::table('purchase_requests')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
            DB::table('ar_invoices')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
            DB::table('ap_invoices')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
        }

        DB::table('purchase_orders')
            ->whereNull('branch_id')
            ->orderBy('id')
            ->chunkById(200, function ($orders) use ($defaultBranchId) {
                foreach ($orders as $order) {
                    $branchId = DB::table('purchase_requests')
                        ->where('id', $order->purchase_request_id)
                        ->value('branch_id');

                    $branchId = (int) ($branchId ?: $defaultBranchId);
                    if ($branchId > 0) {
                        DB::table('purchase_orders')
                            ->where('id', $order->id)
                            ->update(['branch_id' => $branchId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status']);
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('claims', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status']);
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status']);
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('ar_invoices', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status']);
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('ap_invoices', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status']);
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
