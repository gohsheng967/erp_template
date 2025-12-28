<?php

namespace App\Observers;

use App\Models\MilestonePhaseTask;
use App\Services\MilestonePhaseService;
use App\Services\MilestoneService;

class MilestonePhaseTaskObserver
{
    public function saved(MilestonePhaseTask $task): void
    {
        $phase = $task->phase;

        if (!$phase) {
            return;
        }

        // Recalculate phase progress
        app(MilestonePhaseService::class)
            ->recalculateProgress($phase);

        // Recalculate milestone progress
        app(MilestoneService::class)
            ->recalculate($phase->milestone);
    }

    public function deleted(MilestonePhaseTask $task): void
    {
        $phase = $task->phase;

        if (!$phase) {
            return;
        }

        app(MilestonePhaseService::class)
            ->recalculateProgress($phase);

        app(MilestoneService::class)
            ->recalculate($phase->milestone);
    }
}
