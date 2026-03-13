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
                'children:id,uuid,parent_id,title,status,progress_percent',
            ])
            ->where('sub_con_id', $subCon->id)
            ->orderByDesc('id')
            ->get();

        $mainTasks = $tasks->whereNull('parent_id');

        $stats = [
            'total' => $mainTasks->count(),
            'draft' => $mainTasks->where('status', 'draft')->count(),
            'submitted' => $mainTasks->where('status', 'submitted')->count(),
            'contra_verified' => $mainTasks->whereIn('status', ['contra_verified', 'verified'])->count(),
            'invoiced' => $mainTasks->where('status', 'invoiced')->count(),
            'payment' => $mainTasks->whereIn('status', ['approved', 'justified', 'certified', 'paid'])->count(),
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

        if ($task->status !== 'draft') {
            return back()->withErrors([
                'status' => 'Progress can only be submitted while task is In Progress.',
            ]);
        }

        if ($task->parent_id) {
            return back()->withErrors([
                'status' => 'Please submit progress from Main Task only.',
            ]);
        }

        $validated = $request->validate([
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'note' => 'nullable|string',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            'sub_task_ids' => 'nullable|array',
            'sub_task_ids.*' => 'integer',
        ]);

        $children = $task->children()
            ->get(['id', 'status']);
        $childIds = $children->pluck('id')->values();
        $hasChildren = $children->isNotEmpty();

        if (!$hasChildren && !isset($validated['progress_percent'])) {
            return back()->withErrors([
                'progress_percent' => 'Progress (%) is required.',
            ]);
        }

        $selectedSubTaskIds = collect($validated['sub_task_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedSubTaskIds->isNotEmpty()) {
            $invalidIds = $selectedSubTaskIds->diff($childIds);
            if ($invalidIds->isNotEmpty()) {
                return back()->withErrors([
                    'sub_task_ids' => 'Some selected sub tasks are invalid for this main task.',
                ]);
            }
        }

        $calculatedProgress = (int) ($validated['progress_percent'] ?? 0);
        $nextStatus = 'draft';
        if ($hasChildren) {
            $doneChildIds = $children
                ->filter(fn ($child) => strtolower((string) $child->status) !== 'draft')
                ->pluck('id');

            $doneAfterSubmit = $doneChildIds
                ->merge($selectedSubTaskIds)
                ->unique()
                ->count();

            $calculatedProgress = (int) round(($doneAfterSubmit / max($childIds->count(), 1)) * 100);
            $nextStatus = $doneAfterSubmit >= $childIds->count() ? 'submitted' : 'draft';
        } else {
            $nextStatus = $calculatedProgress >= 100 ? 'submitted' : 'draft';
        }

        $files = $request->file('attachments', []);
        if (empty($files)) {
            SubConTaskUpdate::create([
                'sub_con_task_id' => $task->id,
                'progress_percent' => $calculatedProgress,
                'note' => $validated['note'] ?? null,
                'attachment_path' => null,
                'attachment_name' => null,
            ]);
        } else {
            foreach ($files as $file) {
                $path = $file->store('sub-con-task-updates', 'public');

                SubConTaskUpdate::create([
                    'sub_con_task_id' => $task->id,
                    'progress_percent' => $calculatedProgress,
                    'note' => $validated['note'] ?? null,
                    'attachment_path' => $path,
                    'attachment_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        $task->update([
            'progress_percent' => $calculatedProgress,
            'status' => $nextStatus,
        ]);

        if ($selectedSubTaskIds->isNotEmpty()) {
            $children = SubConTask::query()
                ->whereIn('id', $selectedSubTaskIds)
                ->get();

            foreach ($children as $child) {
                $child->update([
                    'progress_percent' => 100,
                    'status' => 'submitted',
                ]);

                SubConTaskUpdate::create([
                    'sub_con_task_id' => $child->id,
                    'progress_percent' => 100,
                    'note' => 'Marked complete from main task submission.',
                    'attachment_path' => null,
                    'attachment_name' => null,
                ]);
            }
        }

        $task->refreshProgressFromChildren();

        return back()->with(
            'success',
            $nextStatus === 'submitted'
                ? 'Progress submitted successfully.'
                : 'Progress saved. Task remains In Progress until all sub tasks are checked.'
        );
    }

    public function storeInvoice(Request $request, string $taskUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $task = SubConTask::query()
            ->where('uuid', $taskUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        if (!in_array($task->status, ['contra_verified', 'verified'], true)) {
            return back()->withErrors([
                'status' => 'Invoice can only be submitted after Contra Verified.',
            ]);
        }

        $validated = $request->validate([
            'invoice_no' => 'required|string|max:255',
            'invoice_date' => 'required|date',
            'invoice_amount' => 'required|numeric|min:0',
            'invoice_remark' => 'nullable|string|max:1000',
            'invoice_attachment' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $file = $request->file('invoice_attachment');
        $path = $file->store('sub-con-task-invoices', 'public');

        $task->update([
            'status' => 'invoiced',
            'invoice_no' => $validated['invoice_no'],
            'invoice_date' => $validated['invoice_date'],
            'invoice_amount' => $validated['invoice_amount'],
            'invoice_remark' => $validated['invoice_remark'] ?? null,
            'invoice_attachment_path' => $path,
            'invoice_attachment_name' => $file->getClientOriginalName(),
            'invoice_submitted_at' => now(),
        ]);

        SubConTask::refreshParentProgressFor($task->parent_id);

        return back()->with('success', 'Invoice submitted successfully.');
    }

    public function downloadInvoice(Request $request, string $taskUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $task = SubConTask::query()
            ->where('uuid', $taskUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        if (!$task->invoice_attachment_path || !Storage::disk('public')->exists($task->invoice_attachment_path)) {
            abort(404, 'Invoice attachment not found.');
        }

        $name = $task->invoice_attachment_name ?? basename($task->invoice_attachment_path);

        return Storage::disk('public')->download($task->invoice_attachment_path, $name);
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

