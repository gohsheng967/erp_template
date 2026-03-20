<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_vehicle_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();
            $table->dateTime('trip_date');
            $table->decimal('mileage', 10, 1)->nullable();
            $table->string('destination');
            $table->text('purpose')->nullable();
            $table->enum('bound_to_type', ['office', 'project', 'others'])->default('office');
            $table->foreignId('bound_to_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('bound_to_label')->nullable();
            $table->foreignId('driver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('driver_name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['inventory_item_id', 'trip_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_vehicle_logs');
    }
};
