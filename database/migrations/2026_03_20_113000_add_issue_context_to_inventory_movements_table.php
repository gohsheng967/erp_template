<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->enum('issue_destination_type', ['office', 'project'])
                ->nullable()
                ->after('serial_number');

            $table->foreignId('project_id')
                ->nullable()
                ->after('issue_destination_type')
                ->constrained('projects')
                ->nullOnDelete();

            $table->foreignId('site_id')
                ->nullable()
                ->after('project_id')
                ->constrained('sites')
                ->nullOnDelete();

            $table->foreignId('issued_by')
                ->nullable()
                ->after('site_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->text('purpose')
                ->nullable()
                ->after('remark');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_by');
            $table->dropConstrainedForeignId('site_id');
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn('issue_destination_type');
            $table->dropColumn('purpose');
        });
    }
};
