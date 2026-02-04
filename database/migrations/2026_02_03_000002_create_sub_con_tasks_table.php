<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_con_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sub_con_id')->constrained('sub_cons')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('sub_con_tasks')->nullOnDelete();

            $table->string('title');
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('status')->default('draft');

            $table->string('payment_cert_no')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('justified_at')->nullable();
            $table->timestamp('certified_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'sub_con_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_con_tasks');
    }
};
