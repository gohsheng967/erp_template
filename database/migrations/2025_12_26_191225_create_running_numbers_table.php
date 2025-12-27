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
        Schema::create('running_numbers', function (Blueprint $table) {
            $table->id();

            $table->string('document_type', 50); // claim, po, invoice, etc
            $table->string('prefix', 20);        // CLM, PO, INV
            $table->unsignedInteger('year');     // 2025
            $table->unsignedInteger('current_no')->default(0);

            $table->timestamps();

            $table->unique(['document_type', 'year']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('running_numbers');
    }
};
