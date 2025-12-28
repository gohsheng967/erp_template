<?php

namespace App\Observers;

use App\Models\MilestonePhase;
use App\Services\MilestoneService;

class MilestonePhaseObserver
{
    public function saved(MilestonePhase $phase): void
    {
        // Any status / progress / position change
        app(MilestoneService::class)
            ->recalculate($phase->milestone);
    }

    public function deleted(MilestonePhase $phase): void
    {
        app(MilestoneService::class)
            ->recalculate($phase->milestone);
    }
}
