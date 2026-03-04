<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('running_numbers', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('id')
                ->constrained('branches')
                ->nullOnDelete();

            $table->dropUnique(['document_type', 'year']);
            $table->unique(['branch_id', 'document_type', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('running_numbers', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'document_type', 'year']);
            $table->unique(['document_type', 'year']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};

