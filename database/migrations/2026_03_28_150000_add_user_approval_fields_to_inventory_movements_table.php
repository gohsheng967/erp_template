<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('user_approved_by')
                ->nullable()
                ->after('issuer_approved_at');
            $table->timestamp('user_approved_at')
                ->nullable()
                ->after('user_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn([
                'user_approved_by',
                'user_approved_at',
            ]);
        });
    }
};
