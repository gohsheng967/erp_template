<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_con_task_updates', function (Blueprint $table) {
            $table->string('progress_report_path')->nullable()->after('note');
            $table->string('progress_report_name')->nullable()->after('progress_report_path');
        });
    }

    public function down(): void
    {
        Schema::table('sub_con_task_updates', function (Blueprint $table) {
            $table->dropColumn([
                'progress_report_path',
                'progress_report_name',
            ]);
        });
    }
};
