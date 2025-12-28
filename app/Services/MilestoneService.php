<?php

namespace App\Services;

use App\Models\Milestone;
use Illuminate\Support\Facades\DB;

class MilestoneService
{
    /**
     * Recalculate milestone progress & status
     */
    public function recalculate(Milestone $milestone): void
    {
        DB::transaction(function () use ($milestone) {

            $phases = $milestone->phases()
                ->where('status', '!=', 'skipped')
                ->get();

            if ($phases->isEmpty()) {
                $milestone->update([
                    'progress' => 0,
                    'status'   => 'draft',
                ]);
                return;
            }

            $progress = (int) floor(
                $phases->avg('progress')
            );

            $allApproved = $phases->every(
                fn ($phase) => $phase->status === 'approved'
            );

            $milestone->update([
                'progress' => $progress,
                'status'   => $allApproved ? 'completed' : 'in_progress',
            ]);
        });
    }
}
