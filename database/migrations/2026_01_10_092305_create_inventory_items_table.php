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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('type'); // vehicle, laptop, phone, equipment
            $table->string('code')->nullable(); // internal asset code

            $table->string('name'); // display name
            $table->string('brand')->nullable();
            $table->string('model')->nullable();

            $table->enum('ownership_type', ['company', 'individual'])->default('company');
            $table->string('owner_name')->nullable();

            $table->enum('status', ['active', 'inactive', 'disposed'])->default('active');

            $table->text('remark')->nullable();

            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
