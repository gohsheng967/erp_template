<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_con_tasks', function (Blueprint $table) {
            $table->string('task_no')->nullable()->unique()->after('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('sub_con_tasks', function (Blueprint $table) {
            $table->dropUnique(['task_no']);
            $table->dropColumn('task_no');
        });
    }
};

