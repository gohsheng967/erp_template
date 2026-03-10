<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_superadmin')
                ->default(false)
                ->after('status');
        });

        DB::statement("
            UPDATE users
            SET is_superadmin = 1
            WHERE id IN (
                SELECT DISTINCT pud.user_id
                FROM pivot_user_departments pud
                LEFT JOIN departments d ON d.id = pud.department_id
                LEFT JOIN roles r ON r.id = pud.role_id
                WHERE LOWER(COALESCE(d.name, '')) IN ('superadmin', 'super admin')
                   OR LOWER(COALESCE(r.name, '')) IN ('superadmin', 'super admin')
            )
        ");

        DB::table('departments')
            ->whereRaw('LOWER(name) IN (?, ?)', ['superadmin', 'super admin'])
            ->delete();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_superadmin');
        });
    }
};
