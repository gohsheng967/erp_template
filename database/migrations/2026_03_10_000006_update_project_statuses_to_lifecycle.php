<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('projects')->where('status', 'draft')->update(['status' => 'incoming']);
        DB::table('projects')->where('status', 'active')->update(['status' => 'on_going']);
        DB::table('projects')->where('status', 'on_hold')->update(['status' => 'extended']);
        DB::table('projects')->whereIn('status', ['completed', 'cancelled'])->update(['status' => 'finished']);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `projects` MODIFY `status` ENUM('incoming','on_going','late','extended','finished') NOT NULL DEFAULT 'incoming'");
        }
    }

    public function down(): void
    {
        DB::table('projects')->where('status', 'incoming')->update(['status' => 'draft']);
        DB::table('projects')->where('status', 'on_going')->update(['status' => 'active']);
        DB::table('projects')->whereIn('status', ['late', 'extended'])->update(['status' => 'on_hold']);
        DB::table('projects')->where('status', 'finished')->update(['status' => 'completed']);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `projects` MODIFY `status` ENUM('draft','active','on_hold','completed','cancelled') NOT NULL DEFAULT 'draft'");
        }
    }
};
