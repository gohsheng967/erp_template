<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');

            // Milestone Type: claim / task / timeline
            $table->enum('type', ['claim', 'task', 'timeline'])->default('task');

            $table->string('title');

            // For claim-based milestone
            $table->decimal('amount', 12, 2)->nullable();

            // For timeline-based milestone
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->date('due_date')->nullable();

            $table->enum('status', [
                'pending', 'in_progress', 'completed', 'submitted', 'paid'
            ])->default('pending');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_milestones');
    }
};
