<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();

            // Link to project (optional)
            $table->unsignedBigInteger('project_id')->nullable();

            $table->string('item');
            $table->integer('quantity')->default(1);
            $table->decimal('est_price', 12, 2)->default(0);
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();

            $table->enum('status', [
                'submitted',
            ])->default('submitted');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->onDelete('set null');  
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
