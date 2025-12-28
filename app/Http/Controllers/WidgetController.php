<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MilestoneActionTask;

class WidgetController extends Controller
{
    public function actionTask(Request $request)
    {
        $user = $request->user();

        $baseQuery = MilestoneActionTask::query()
            ->where('is_done', false)
            ->where(function ($q) use ($user) {
                $q->whereNull('assigned_to')
                  ->orWhere('assigned_to', $user->id);
            });

        return response()->json([
            'urgent'   => (clone $baseQuery)->where('priority', 'urgent')->count(),
            'moderate' => (clone $baseQuery)->where('priority', 'moderate')->count(),
            'low'      => (clone $baseQuery)->where('priority', 'low')->count(),
            'total'    => $baseQuery->count(),
        ]);
    }

    public function actionTaskList(Request $request)
    {
        $user = $request->user();
        $priority = $request->get('priority');

        $tasks = MilestoneActionTask::query()
            ->where('is_done', false)
            ->when($priority, fn ($q) => $q->where('priority', $priority))
            ->where(function ($q) use ($user) {
                $q->whereNull('assigned_to')
                ->orWhere('assigned_to', $user->id);
            })
            ->with([
                'milestone.project:id,name,uuid'
            ])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id'            => $t->id,
                'title'         => $t->title,
                'project'       => optional($t->milestone?->project)->name,
                'project_uuid'  => optional($t->milestone?->project)->uuid,
            ]);

        return response()->json($tasks);
    }


}
