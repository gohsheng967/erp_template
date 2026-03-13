<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_deliveries', 'eod_date')) {
                $table->date('eod_date')->nullable()->after('delivery_date')->index();
            }
        });

        DB::statement("
            ALTER TABLE purchase_deliveries
            MODIFY status ENUM('preparation','transit','warehouse','returned') NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE purchase_deliveries
            MODIFY status ENUM('transit','warehouse','returned') NOT NULL
        ");

        Schema::table('purchase_deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_deliveries', 'eod_date')) {
                $table->dropColumn('eod_date');
            }
        });
    }
};

