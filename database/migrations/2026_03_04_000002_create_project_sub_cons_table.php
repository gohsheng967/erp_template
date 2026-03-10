<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_sub_cons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('sub_con_id')->constrained('sub_cons')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'sub_con_id']);
        });

        $existingPairs = DB::table('sub_con_tasks')
            ->select('project_id', 'sub_con_id')
            ->whereNotNull('project_id')
            ->whereNotNull('sub_con_id')
            ->distinct()
            ->get();

        foreach ($existingPairs as $pair) {
            DB::table('project_sub_cons')->insert([
                'project_id' => $pair->project_id,
                'sub_con_id' => $pair->sub_con_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_sub_cons');
    }
};
