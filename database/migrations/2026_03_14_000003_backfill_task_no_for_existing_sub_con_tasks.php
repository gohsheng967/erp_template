<?php

use App\Services\RunningNumberService;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('sub_con_tasks as t')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->leftJoin('branches as b', 'b.id', '=', 'p.branch_id')
            ->whereNull('t.task_no')
            ->orderBy('t.id')
            ->select([
                't.id',
                't.created_at',
                'p.branch_id',
                'b.slug as branch_slug',
            ])
            ->get();

        foreach ($rows as $row) {
            $branchId = (int) ($row->branch_id ?? 0);
            $branchSlug = (string) ($row->branch_slug ?? '');

            if ($branchId <= 0 || $branchSlug === '') {
                continue;
            }

            $year = $row->created_at
                ? Carbon::parse($row->created_at)->year
                : now()->year;

            $taskNo = RunningNumberService::next(
                documentType: 'sub_con_task',
                year: $year,
                branchId: $branchId,
                branchSlug: $branchSlug
            );

            DB::table('sub_con_tasks')
                ->where('id', $row->id)
                ->whereNull('task_no')
                ->update([
                    'task_no' => $taskNo,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Keep existing generated task numbers on rollback.
    }
};
