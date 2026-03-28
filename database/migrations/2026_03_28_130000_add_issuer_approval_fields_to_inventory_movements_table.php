<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('issuer_id')
                ->nullable()
                ->after('issued_by');
            $table->unsignedBigInteger('issuer_approved_by')
                ->nullable()
                ->after('issuer_signature_path');
            $table->timestamp('issuer_approved_at')
                ->nullable()
                ->after('issuer_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn([
                'issuer_id',
                'issuer_approved_by',
                'issuer_approved_at',
            ]);
        });
    }
};
