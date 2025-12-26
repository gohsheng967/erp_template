<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();

            $table->string('label');          // Display Label
            $table->string('field_name');     // System Key
            $table->string('field_type');     // text, number, date, file, select
            $table->boolean('is_required')->default(false);
            $table->json('options')->nullable();
            $table->integer('order')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_fields');
    }
};
