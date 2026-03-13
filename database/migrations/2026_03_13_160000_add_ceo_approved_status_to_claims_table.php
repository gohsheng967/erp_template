<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE claims
            MODIFY COLUMN status ENUM(
                'draft',
                'submitted',
                'checked',
                'verified',
                'approved',
                'ceo_approved',
                'paid',
                'rejected'
            ) NOT NULL DEFAULT 'submitted'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE claims
            MODIFY COLUMN status ENUM(
                'draft',
                'submitted',
                'checked',
                'verified',
                'approved',
                'paid',
                'rejected'
            ) NOT NULL DEFAULT 'submitted'
        ");
    }
};
