<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_vehicle_logs', function (Blueprint $table) {
            $table->dateTime('started_at')->nullable()->after('trip_date');
            $table->dateTime('ended_at')->nullable()->after('started_at');
            $table->decimal('start_mileage', 10, 1)->nullable()->after('mileage');
            $table->decimal('end_mileage', 10, 1)->nullable()->after('start_mileage');
            $table->string('trip_status', 20)->default('completed')->after('purpose');
            $table->boolean('pin_bypassed')->default(false)->after('trip_status');

            $table->index(['inventory_item_id', 'trip_status'], 'inventory_vehicle_logs_item_status_idx');
            $table->index(['inventory_item_id', 'started_at'], 'inventory_vehicle_logs_item_started_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_vehicle_logs', function (Blueprint $table) {
            $table->dropIndex('inventory_vehicle_logs_item_status_idx');
            $table->dropIndex('inventory_vehicle_logs_item_started_idx');
            $table->dropColumn([
                'started_at',
                'ended_at',
                'start_mileage',
                'end_mileage',
                'trip_status',
                'pin_bypassed',
            ]);
        });
    }
};

