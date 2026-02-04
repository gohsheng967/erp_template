<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_con_task_updates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('sub_con_task_id')
                ->constrained('sub_con_tasks')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('progress_percent');
            $table->text('note')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();

            $table->timestamps();

            $table->index(['sub_con_task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_con_task_updates');
    }
};
