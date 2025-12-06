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
        Schema::create('file_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('file_categories')->nullOnDelete();

            // Settings
            $table->json('allowed_extensions')->nullable(); // ["pdf","png","docx"]
            $table->integer('max_size')->default(5120); // KB
            $table->enum('visibility', ['public', 'department', 'role'])->default('public');

            // Optional restriction
            $table->json('allowed_departments')->nullable(); // [1,2,7]
            $table->json('allowed_roles')->nullable(); // [5,8,9]

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_categories');
    }
};
