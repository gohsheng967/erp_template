<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultBranchId = DB::table('branches')
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        if (!$defaultBranchId) {
            $defaultBranchId = DB::table('branches')
                ->orderBy('id')
                ->value('id');
        }

        if (!$defaultBranchId) {
            $now = now();
            $defaultBranchId = DB::table('branches')->insertGetId([
                'name' => 'Default Branch',
                'slug' => 'default',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('users')
            ->whereNull('active_branch_id')
            ->update(['active_branch_id' => $defaultBranchId]);

        DB::statement("
            INSERT INTO pivot_user_branches (user_id, branch_id, created_at, updated_at)
            SELECT u.id, u.active_branch_id, NOW(), NOW()
            FROM users u
            WHERE u.active_branch_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM pivot_user_branches p
                  WHERE p.user_id = u.id
                    AND p.branch_id = u.active_branch_id
              )
        ");

        Schema::table('users', function ($table) {
            $table->dropForeign(['active_branch_id']);
        });

        DB::statement('ALTER TABLE users MODIFY active_branch_id BIGINT UNSIGNED NOT NULL');

        Schema::table('users', function ($table) {
            $table->foreign('active_branch_id')
                ->references('id')
                ->on('branches')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function ($table) {
            $table->dropForeign(['active_branch_id']);
        });

        DB::statement('ALTER TABLE users MODIFY active_branch_id BIGINT UNSIGNED NULL');

        Schema::table('users', function ($table) {
            $table->foreign('active_branch_id')
                ->references('id')
                ->on('branches')
                ->nullOnDelete();
        });
    }
};
