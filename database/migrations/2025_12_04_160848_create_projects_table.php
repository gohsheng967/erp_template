<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->decimal('budget', 12, 2)->default(0);

            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();

            $table->enum('status', [
                'draft', 'active', 'on_hold', 'completed', 'cancelled'
            ])->default('draft');

            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'department_id', 'manager_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
