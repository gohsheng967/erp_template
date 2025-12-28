<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('milestone_action_tasks', function (Blueprint $table) {

            $table->string('status')
                ->default('open')
                ->after('is_done');

            $table->text('remark')
                ->nullable()
                ->after('status');

            $table->timestamp('completed_at')
                ->nullable()
                ->after('remark');

            $table->timestamp('reviewed_at')
                ->nullable()
                ->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('milestone_action_tasks', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'remark',
                'completed_at',
                'reviewed_at',
            ]);
        });
    }

};
