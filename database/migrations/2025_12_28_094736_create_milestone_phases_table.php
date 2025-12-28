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
        Schema::create('milestone_phases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('milestone_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // ordering for drag & drop
            $table->unsignedInteger('position')->default(0);

            // pending | approved | attention | skipped
            $table->string('status')->default('pending');

            // cached progress from phase tasks
            $table->unsignedTinyInteger('progress')->default(0);

            // free text for now (role excluded)
            $table->string('reviewer')->nullable();

            // required if skipped
            $table->text('skip_reason')->nullable();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestone_phases');
    }
};
