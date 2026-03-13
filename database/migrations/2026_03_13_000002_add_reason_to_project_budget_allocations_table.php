<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_budget_allocations', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('new_budget');
        });
    }

    public function down(): void
    {
        Schema::table('project_budget_allocations', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};

