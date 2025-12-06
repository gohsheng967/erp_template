<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('claim_expenses', function (Blueprint $table) {
            $table->id();

            // Link to project (optional)
            $table->unsignedBigInteger('project_id')->nullable();

            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->string('receipt_path')->nullable();
            $table->text('remarks')->nullable();

            $table->enum('status', [
                'submitted', // for now only this
            ])->default('submitted');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->onDelete('set null'); // project deleted → keep the claim
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_expenses');
    }
};
