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
        Schema::create('milestone_phase_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('milestone_phase_id')
                ->constrained('milestone_phases')
                ->cascadeOnDelete();

            $table->string('title');

            $table->boolean('is_done')->default(false);

            // ordering inside phase
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestone_phase_tasks');
    }
};
