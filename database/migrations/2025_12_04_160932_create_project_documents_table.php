<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();

            // 📄 File info
            $table->string('filename');
            $table->string('filepath');
            $table->unsignedBigInteger('filesize')->nullable();

            // 🏷 Meta
            $table->string('type')->nullable();  
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->cascadeOnDelete();

            $table->foreign('category_id')
                ->references('id')->on('file_categories')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_documents');
    }
};
