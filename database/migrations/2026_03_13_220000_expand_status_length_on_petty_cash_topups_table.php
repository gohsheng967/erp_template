<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE petty_cash_topups MODIFY status VARCHAR(50) NOT NULL DEFAULT 'requested'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE petty_cash_topups MODIFY status VARCHAR(20) NOT NULL DEFAULT 'requested'");
    }
};

