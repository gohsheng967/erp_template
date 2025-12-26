<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Polymorphic relation
            $table->morphs('attachable'); 
            // creates: attachable_id, attachable_type

            $table->string('file_path');
            $table->string('original_name');

            // Optional metadata (future-proof)
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->timestamps();

            // Index for performance
            $table->index(['attachable_id', 'attachable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
