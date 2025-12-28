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
        Schema::create('milestone_action_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('milestone_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');

            // low | medium | high | critical
            $table->string('priority')->default('medium');

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('is_done')->default(false);

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestone_action_tasks');
    }
};
