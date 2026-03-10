<?php

namespace App\Http\Controllers;

use App\Models\SubCon;
use App\Models\SubConTask;
use App\Models\SubConTaskUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SubConPortalController extends Controller
{
    public function index(Request $request): Response
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $tasks = SubConTask::query()
            ->with([
                'project:id,uuid,code,name,status',
                'updates',
            ])
            ->where('sub_con_id', $subCon->id)
            ->orderByDesc('id')
            ->get();

        $stats = [
            'total' => $tasks->count(),
            'draft' => $tasks->where('status', 'draft')->count(),
            'submitted' => $tasks->where('status', 'submitted')->count(),
            'verified' => $tasks->where('status', 'verified')->count(),
            'justified' => $tasks->where('status', 'justified')->count(),
            'certified' => $tasks->where('status', 'certified')->count(),
            'paid' => $tasks->where('status', 'paid')->count(),
        ];

        return Inertia::render('SubCon/Portal', [
            'subCon' => [
                'id' => $subCon->id,
                'uuid' => $subCon->uuid,
                'name' => $subCon->name,
                'company_name' => $subCon->company_name,
                'login_identity_no' => $subCon->login_identity_no,
            ],
            'stats' => $stats,
            'tasks' => $tasks,
        ]);
    }

    public function storeUpdate(Request $request, string $taskUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $task = SubConTask::query()
            ->with('project:id,uuid')
            ->where('uuid', $taskUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        if (!in_array($task->status, ['draft', 'submitted'], true)) {
            return back()->withErrors([
                'status' => 'Progress can only be submitted while task is Draft or Submitted.',
            ]);
        }

        $validated = $request->validate([
            'progress_percent' => 'required|integer|min:0|max:100',
            'note' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $path = null;
        $originalName = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $originalName = $file->getClientOriginalName();
            $path = $file->store('sub-con-task-updates', 'public');
        }

        SubConTaskUpdate::create([
            'sub_con_task_id' => $task->id,
            'progress_percent' => $validated['progress_percent'],
            'note' => $validated['note'] ?? null,
            'attachment_path' => $path,
            'attachment_name' => $originalName,
        ]);

        $task->update([
            'progress_percent' => $validated['progress_percent'],
            'status' => 'submitted',
        ]);

        return back()->with('success', 'Progress submitted successfully.');
    }

    public function downloadUpdate(Request $request, string $taskUuid, string $updateUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $task = SubConTask::query()
            ->where('uuid', $taskUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        $update = SubConTaskUpdate::query()
            ->where('uuid', $updateUuid)
            ->where('sub_con_task_id', $task->id)
            ->firstOrFail();

        if (!$update->attachment_path || !Storage::disk('public')->exists($update->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        $name = $update->attachment_name ?? basename($update->attachment_path);

        return Storage::disk('public')->download($update->attachment_path, $name);
    }

    private function getAuthenticatedSubCon(Request $request): SubCon
    {
        $subConId = (int) $request->session()->get('sub_con_auth_id');

        return SubCon::query()
            ->where('id', $subConId)
            ->firstOrFail();
    }
}

