<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Milestone;
use App\Models\MilestoneActionTask;
use App\Models\MilestonePhase;
use App\Models\MilestonePhaseTask;
use App\Services\MilestonePhaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MilestoneController extends Controller
{
    /* =========================
       SHOW (optional standalone)
    ========================= */
    public function show(
        Project $project,
        Milestone $milestone
    ) {
        return Inertia::render('Projects/Milestones/Show', [
            'project'   => $project,
            'milestone' => $milestone->load([
                'actionTasks.assignee',
                'phases.tasks',
            ]),
        ]);
    }

    /* =========================
       CREATE MILESTONE
    ========================= */
    public function store(
        Project $project,
        Request $request
    ) {
        $project->milestones()->create([
            'title'  => $request->input('title', 'New Milestone'),
            'status' => 'draft',
        ]);

        return back();
    }

    /* =========================
       SECTION 1 — ACTION TASKS
    ========================= */
    public function storeActionTask(
        Project $project,
        Request $request,
        Milestone $milestone
    ) {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'priority'    => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $milestone->actionTasks()->create($data);

        return back();
    }

    public function updateActionTask(Request $request, Project $project, MilestoneActionTask $task)
    {
        $task->update(
            $request->validate([
                'title'       => 'sometimes|string|max:255',
                'priority'    => 'sometimes|string',
                'assigned_to' => 'nullable|exists:users,id',
                'is_done'     => 'sometimes|boolean',
                'status'      => 'sometimes|string',
                'remark'      => 'nullable|string',
            ])
        );

        return back();
    }


    public function destroyActionTask(
        Project $project,
        MilestoneActionTask $task
    ) {
        $task->delete();
        return back();
    }

    /* =========================
       SECTION 2 — PHASES
    ========================= */
    public function storePhase(
        Project $project,
        Request $request,
        Milestone $milestone
    ) {
        $data = $request->validate([
            'title'      => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $position = ($milestone->phases()->max('position') ?? 0) + 1;

        $milestone->phases()->create([
            ...$data,
            'position' => $position,
        ]);

        return back();
    }

    public function updatePhase(
        Project $project,
        MilestonePhase $phase
    ) {
        $phase->update(
            request()->validate([
                'title'      => 'sometimes|string|max:255',
                'start_date' => 'nullable|date',
                'end_date'   => 'nullable|date|after_or_equal:start_date',
            ])
        );

        return back();
    }

    public function reorderPhases(
        Project $project,
        Request $request,
        MilestonePhaseService $service
    ) {
        $data = $request->validate([
            'phases'   => 'required|array',
            'phases.*' => 'exists:milestone_phases,id',
        ]);

        $service->reorder($data['phases']);

        return back();
    }

    public function changePhaseStatus(
        Project $project,
        Request $request,
        MilestonePhase $phase,
        MilestonePhaseService $service
    ) {
        $data = $request->validate([
            'status' => 'required|string',
            'note'   => 'nullable|string',
        ]);

        $service->changeStatus(
            $phase,
            $data['status'],
            $data['note'] ?? null
        );

        return back();
    }

    /* =========================
       PHASE TASKS
    ========================= */
    public function storePhaseTask(
        Project $project,
        Request $request,
        MilestonePhase $phase
    ) {
        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $position = ($phase->tasks()->max('position') ?? 0) + 1;

        $phase->tasks()->create([
            'title'    => $data['title'],
            'position' => $position,
        ]);

        return back();
    }

    public function updatePhaseTask(
        Project $project,
        MilestonePhaseTask $task
    ) {
        $task->update(
            request()->validate([
                'title'   => 'sometimes|string|max:255',
                'is_done' => 'sometimes|boolean',
            ])
        );

        return back();
    }

    public function destroyPhaseTask(
        Project $project,
        MilestonePhaseTask $task
    ) {
        $task->delete();
        return back();
    }
}
