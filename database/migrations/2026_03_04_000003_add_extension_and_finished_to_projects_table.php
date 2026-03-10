<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('extension_date')->nullable()->after('end_date');
            $table->boolean('is_finished')->default(false)->after('status');
            $table->timestamp('finished_at')->nullable()->after('is_finished');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['extension_date', 'is_finished', 'finished_at']);
        });
    }
};
