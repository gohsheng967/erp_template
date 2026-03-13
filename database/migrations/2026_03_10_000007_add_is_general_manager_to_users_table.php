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
            $table->boolean('is_general_manager')
                ->default(false)
                ->after('is_superadmin');
        });

        DB::statement("
            UPDATE users
            SET is_general_manager = 1
            WHERE id IN (
                SELECT DISTINCT pud.user_id
                FROM pivot_user_departments pud
                INNER JOIN roles r ON r.id = pud.role_id
                WHERE LOWER(COALESCE(r.name, '')) = 'general manager'
            )
        ");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_general_manager');
        });
    }
};
