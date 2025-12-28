<?php

namespace App\Services;

use App\Models\MilestonePhase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class MilestonePhaseService
{
    /**
     * Recalculate phase progress from tasks
     */
    public function recalculateProgress(MilestonePhase $phase): void
    {
        if ($phase->status === 'approved') {
            return; // progress frozen
        }

        $tasks = $phase->tasks;

        if ($tasks->isEmpty()) {
            $phase->update(['progress' => 0]);
            return;
        }

        $done = $tasks->where('is_done', true)->count();

        $phase->update([
            'progress' => (int) floor(($done / $tasks->count()) * 100),
        ]);
    }

    /**
     * Change phase status
     */
    public function changeStatus(
        MilestonePhase $phase,
        string $status,
        ?string $note = null
    ): void {
        DB::transaction(function () use ($phase, $status, $note) {

            match ($status) {
                'approved'  => $this->approve($phase),
                'attention' => $this->attention($phase),
                'skipped'   => $this->skip($phase, $note),
                default     => throw ValidationException::withMessages([
                    'status' => 'Invalid phase status',
                ]),
            };
        });
    }

    protected function approve(MilestonePhase $phase): void
    {
        if ($phase->tasks()->count() === 0) {
            throw ValidationException::withMessages([
                'phase' => 'Cannot approve phase without tasks',
            ]);
        }

        $phase->update([
            'status' => 'approved',
        ]);
    }

    protected function attention(MilestonePhase $phase): void
    {
        if ($phase->status === 'approved') {
            throw ValidationException::withMessages([
                'phase' => 'Approved phase cannot go back to attention',
            ]);
        }

        $phase->update([
            'status' => 'attention',
        ]);
    }

    protected function skip(MilestonePhase $phase, ?string $reason): void
    {
        if (!$reason) {
            throw ValidationException::withMessages([
                'skip_reason' => 'Skip reason is required',
            ]);
        }

        $phase->update([
            'status'      => 'skipped',
            'skip_reason' => $reason,
            'progress'    => 0,
        ]);
    }

    /**
     * Reorder phases
     */
    public function reorder(array $phaseIds): void
    {
        DB::transaction(function () use ($phaseIds) {

            foreach ($phaseIds as $index => $id) {
                MilestonePhase::where('id', $id)->update([
                    'position' => $index + 1,
                ]);
            }
        });
    }
}
