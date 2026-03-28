<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('destination_user_id')
                ->nullable()
                ->after('site_id');
            $table->unsignedBigInteger('holder_user_id')
                ->nullable()
                ->after('destination_user_id');
            $table->string('usage_status', 20)
                ->default('active')
                ->after('holder_user_id');
            $table->string('usage_action', 20)
                ->nullable()
                ->after('usage_status');
            $table->text('usage_remark')
                ->nullable()
                ->after('usage_action');
            $table->unsignedBigInteger('usage_updated_by')
                ->nullable()
                ->after('usage_remark');
            $table->timestamp('usage_updated_at')
                ->nullable()
                ->after('usage_updated_by');
        });

        DB::statement("ALTER TABLE inventory_movements MODIFY issue_destination_type ENUM('office','project','user') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE inventory_movements MODIFY issue_destination_type ENUM('office','project') NULL");

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn([
                'destination_user_id',
                'holder_user_id',
                'usage_status',
                'usage_action',
                'usage_remark',
                'usage_updated_by',
                'usage_updated_at',
            ]);
        });
    }
};
