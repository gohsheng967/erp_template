<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\SubConTask;
use App\Models\SubConTaskUpdate;
use App\Models\PaymentSlip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\AttachmentService;
use App\Services\RunningNumberService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubConTaskController extends Controller
{
    public function index(string $projectUuid)
    {
        $this->ensureInternalUser(request());

        $project = $this->resolveProject(request(), $projectUuid);

        $tasks = SubConTask::with(['subCon:id,uuid,name,company_name', 'parent:id,uuid,title', 'updates'])
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->get();

        return response()->json($tasks);
    }

    public function store(Request $request, string $projectUuid)
    {
        $this->ensureInternalUser($request);

        $project = $this->resolveProject($request, $projectUuid);

        $validated = $request->validate([
            'sub_con_id' => 'required|integer|exists:sub_cons,id',
            'title' => 'required|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'parent_id' => 'nullable|integer|exists:sub_con_tasks,id',
        ]);

        if (empty($validated['parent_id']) && !isset($validated['amount'])) {
            return back()->withErrors([
                'amount' => 'Amount is required for main task.',
            ]);
        }

        $isBound = $project->subCons()
            ->where('sub_cons.id', $validated['sub_con_id'])
            ->exists();

        if (!$isBound) {
            return back()->withErrors([
                'sub_con_id' => 'Selected Sub Con is not bound to this project.',
            ]);
        }

        if (!empty($validated['parent_id'])) {
            $parent = SubConTask::where('id', $validated['parent_id'])
                ->where('project_id', $project->id)
                ->first();

            if (!$parent) {
                return back()->withErrors([
                    'parent_id' => 'Parent task does not belong to this project.',
                ]);
            }

            if ($parent->parent_id) {
                return back()->withErrors([
                    'parent_id' => 'Parent task must be a main task.',
                ]);
            }
        }

        $createdTask = SubConTask::create([
            'task_no' => RunningNumberService::next('sub_con_task'),
            'project_id' => $project->id,
            'sub_con_id' => $validated['sub_con_id'],
            'parent_id' => $validated['parent_id'] ?? null,
            'title' => $validated['title'],
            'amount' => empty($validated['parent_id'])
                ? (float) ($validated['amount'] ?? 0)
                : 0,
            'status' => 'draft',
            'progress_percent' => 0,
        ]);

        SubConTask::refreshParentProgressFor($createdTask->parent_id);

        return back()->with('success', 'Sub Con task created successfully.');
    }

    public function storeUpdate(Request $request, string $projectUuid, string $taskUuid)
    {
        $project = $this->resolveProject($request, $projectUuid);

        $task = SubConTask::where('uuid', $taskUuid)
            ->where('project_id', $project->id)
            ->firstOrFail();

        $this->ensureSubConOwnsTask($request, $task);

        if ($task->status !== 'draft') {
            return back()->withErrors([
                'status' => 'Progress can only be submitted while task is In Progress.',
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

        SubConTask::refreshParentProgressFor($task->parent_id);

        return back()->with('success', 'Progress submitted successfully.');
    }

    public function verify(Request $request, string $projectUuid, string $taskUuid)
    {
        $this->ensureInternalUser($request);

        $task = $this->getTask($request, $projectUuid, $taskUuid);

        if ($task->status !== 'submitted') {
            return back()->withErrors([
                'status' => 'Only submitted progress can be verified.',
            ]);
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['action'] === 'reject') {
            if (empty($validated['remark'])) {
                return back()->withErrors([
                    'remark' => 'Remark is required to reject.',
                ]);
            }

            $task->update([
                'status' => 'draft',
                'verified_at' => null,
                'verified_remark' => null,
                'verified_reject_remark' => $validated['remark'],
            ]);

            SubConTask::refreshParentProgressFor($task->parent_id);

            return back()->with('success', 'Progress rejected. Sub Con needs to submit again.');
        }

        $task->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_remark' => $validated['remark'] ?? null,
            'verified_reject_remark' => null,
        ]);

        SubConTask::refreshParentProgressFor($task->parent_id);

        return back()->with('success', 'Progress verified.');
    }

    public function generateFromPo(Request $request, string $projectUuid)
    {
        $this->ensureInternalUser($request);

        $project = $this->resolveProject($request, $projectUuid);

        $validated = $request->validate([
            'purchase_order_id' => ['required', 'integer', Rule::exists('purchase_orders', 'id')],
            'sub_con_id' => ['required', 'integer', Rule::exists('sub_cons', 'id')],
        ]);

        $isBound = $project->subCons()
            ->where('sub_cons.id', $validated['sub_con_id'])
            ->exists();

        if (!$isBound) {
            return back()->withErrors([
                'sub_con_id' => 'Selected Sub Con is not bound to this project.',
            ]);
        }

        $po = PurchaseOrder::query()
            ->with([
                'purchaseRequest:id',
                'items:id,purchase_order_id,purchase_request_item_id,item_name,description,quantity,unit_price',
                'items.purchaseRequestItem:id,parent_id,title',
            ])
            ->whereKey($validated['purchase_order_id'])
            ->where(function ($query) {
                $query->whereNotNull('confirmed_at')
                    ->orWhere('status', 'confirmed');
            })
            ->whereHas('purchaseRequest', function ($query) use ($project) {
                $query->where('project_id', $project->id);
            })
            ->first();

        if (!$po) {
            return back()->withErrors([
                'purchase_order_id' => 'Selected PO must be confirmed and belong to this project.',
            ]);
        }

        if ($po->items->isEmpty()) {
            return back()->withErrors([
                'purchase_order_id' => 'Selected PO has no items to generate tasks.',
            ]);
        }

        $created = 0;

        $prItems = $po->purchaseRequest
            ? $po->purchaseRequest->items()->select('id', 'parent_id', 'title')->get()->keyBy('id')
            : collect();

        $poPrefix = (string) ($po->code ?? ('PO-' . $po->id));
        $mappedGroups = [];
        $unmappedPoItems = [];

        foreach ($po->items as $poItem) {
            $prItemId = (int) ($poItem->purchase_request_item_id ?? 0);
            $amount = max(0, (float) $poItem->quantity * (float) $poItem->unit_price);

            if ($prItemId <= 0 || !$prItems->has($prItemId)) {
                $unmappedPoItems[] = $poItem;
                continue;
            }

            $rootId = $prItemId;
            $walkerId = $prItemId;
            while ($walkerId && $prItems->has($walkerId)) {
                $parentId = (int) ($prItems[$walkerId]->parent_id ?? 0);
                if ($parentId <= 0 || !$prItems->has($parentId)) {
                    break;
                }
                $rootId = $parentId;
                $walkerId = $parentId;
            }

            if (!isset($mappedGroups[$rootId])) {
                $mappedGroups[$rootId] = [
                    'amount' => 0,
                    'items' => [],
                ];
            }

            $mappedGroups[$rootId]['amount'] += $amount;
            $mappedGroups[$rootId]['items'][] = $poItem;
        }

        foreach ($mappedGroups as $rootPrItemId => $group) {
            $rootPrItem = $prItems->get($rootPrItemId);
            $parentTitle = trim((string) ($rootPrItem?->title ?? ''));
            if ($parentTitle === '') {
                $parentTitle = 'PO Item Group #' . $rootPrItemId;
            }

            $parentTask = SubConTask::create([
                'task_no' => RunningNumberService::next('sub_con_task'),
                'project_id' => $project->id,
                'sub_con_id' => $validated['sub_con_id'],
                'parent_id' => null,
                'title' => sprintf('[%s] %s', $poPrefix, $parentTitle),
                'amount' => max(0, (float) $group['amount']),
                'status' => 'draft',
                'progress_percent' => 0,
            ]);
            $created++;

            foreach ($group['items'] as $poItem) {
                $prItemId = (int) ($poItem->purchase_request_item_id ?? 0);
                $prItem = $prItems->get($prItemId);

                // Root PR item is represented by main task itself; only children become sub tasks.
                if (!$prItem || empty($prItem->parent_id)) {
                    continue;
                }

                $childTitle = trim((string) ($poItem->item_name ?? $prItem->title ?? ''));
                if ($childTitle === '') {
                    $childTitle = 'PO Item #' . $poItem->id;
                }

                $description = trim((string) ($poItem->description ?? ''));

                SubConTask::create([
                    'task_no' => RunningNumberService::next('sub_con_task'),
                    'project_id' => $project->id,
                    'sub_con_id' => $validated['sub_con_id'],
                    'parent_id' => $parentTask->id,
                    'title' => $description !== '' ? ($childTitle . ' - ' . $description) : $childTitle,
                    'amount' => 0,
                    'status' => 'draft',
                    'progress_percent' => 0,
                ]);
                $created++;
            }
        }

        foreach ($unmappedPoItems as $item) {
            $title = trim((string) ($item->item_name ?? ''));
            if ($title === '') {
                $title = 'PO Item #' . $item->id;
            }

            $amount = max(0, (float) $item->quantity * (float) $item->unit_price);
            $description = trim((string) ($item->description ?? ''));

            SubConTask::create([
                'task_no' => RunningNumberService::next('sub_con_task'),
                'project_id' => $project->id,
                'sub_con_id' => $validated['sub_con_id'],
                'parent_id' => null,
                'title' => sprintf(
                    '[%s] %s%s',
                    $poPrefix,
                    $title,
                    $description !== '' ? (' - ' . $description) : ''
                ),
                'amount' => $amount,
                'status' => 'draft',
                'progress_percent' => 0,
            ]);

            $created++;
        }

        return back()->with('success', "Generated {$created} task(s) from PO {$po->code}.");
    }

    public function justify(Request $request, string $projectUuid, string $taskUuid)
    {
        $this->ensureInternalUser($request);
        $this->ensureCeoOrGm($request);

        $task = $this->getTask($request, $projectUuid, $taskUuid);

        if (!in_array($task->status, ['invoiced'], true)) {
            return back()->withErrors([
                'status' => 'Only invoiced tasks can be approved.',
            ]);
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['action'] === 'reject') {
            if (empty($validated['remark'])) {
                return back()->withErrors([
                    'remark' => 'Remark is required to reject.',
                ]);
            }

            $task->update([
                'status' => 'contra_verified',
                'justified_at' => null,
                'justified_remark' => null,
                'justified_reject_remark' => $validated['remark'],
            ]);

            SubConTask::refreshParentProgressFor($task->parent_id);

            return back()->with('success', 'Approval rejected. Task moved back to Contra Verified.');
        }

        $task->update([
            'status' => 'approved',
            'justified_at' => now(),
            'justified_remark' => $validated['remark'] ?? null,
            'justified_reject_remark' => null,
        ]);

        SubConTask::refreshParentProgressFor($task->parent_id);

        return back()->with('success', 'Task approved by CEO/GM.');
    }

    public function certify(Request $request, string $projectUuid, string $taskUuid)
    {
        $this->ensureInternalUser($request);

        $task = $this->getTask($request, $projectUuid, $taskUuid);
        $projectBranchId = (int) (Project::query()->whereKey($task->project_id)->value('branch_id') ?? 0);

        if (!in_array($task->status, ['approved', 'justified', 'certified'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only approved or certified tasks can issue payment cert.',
            ]);
        }

        $validated = $request->validate([
            'company_bank_account_id' => [
                'required',
                Rule::exists('company_bank_accounts', 'id')->where(function ($query) use ($request, $projectBranchId) {
                    $query
                        ->where('status', 'active');

                    $activeBranchId = (int) ($request->user()?->active_branch_id ?? 0);
                    $resolvedBranchId = $activeBranchId > 0 ? $activeBranchId : $projectBranchId;

                    if ($resolvedBranchId > 0) {
                        $query->where('branch_id', $resolvedBranchId);
                    }
                }),
            ],
            'less_retention' => ['nullable', 'numeric', 'min:0'],
            'less_recoupment' => ['nullable', 'numeric', 'min:0'],
            'less_material_ob' => ['nullable', 'numeric', 'min:0'],
            'less_paid_previously' => ['nullable', 'numeric', 'min:0'],
            'payment_slip_remark' => ['nullable', 'string', 'max:255'],
        ]);

        $slip = $task->paymentSlip ?? new PaymentSlip();
        if (!$slip->exists) {
            $slip->slip_no = RunningNumberService::next('payment_slip');
            $slip->source()->associate($task);
        } elseif ($slip->cancelled_at) {
            $slip->slip_no = RunningNumberService::next('payment_slip');
            $slip->cancelled_at = null;
            $slip->cancelled_by = null;
            $slip->cancel_reason = null;
        }

        $slip->company_bank_account_id = $validated['company_bank_account_id'];
        $slip->amount = (float) ($task->invoice_amount ?? $task->amount);
        $slip->payment_date = now()->toDateString();
        $slip->less_retention = $request->input('less_retention');
        $slip->less_recoupment = $request->input('less_recoupment');
        $slip->less_material_ob = $request->input('less_material_ob');
        $slip->less_paid_previously = $request->input('less_paid_previously');
        $slip->payment_slip_remark = $request->input('payment_slip_remark');
        $slip->workflow_status = 'processing';
        $slip->created_by = $request->user()?->id;
        $slip->save();

        $task->update([
            'status' => 'certified',
            'payment_cert_no' => $slip->slip_no,
            'payment_slip_no' => $slip->slip_no,
            'certified_at' => now(),
        ]);

        SubConTask::refreshParentProgressFor($task->parent_id);

        $slip->load([
            'companyBankAccount',
            'creator:id,name,signature_path',
            'approvedBy:id,name,signature_path',
            'source.project',
            'source.subCon',
        ]);

        return response()->json([
            'slip' => $slip,
        ]);
    }

    public function paid(Request $request, string $projectUuid, string $taskUuid)
    {
        $this->ensureInternalUser($request);

        $task = $this->getTask($request, $projectUuid, $taskUuid);

        if ($task->status !== 'certified') {
            return back()->withErrors([
                'status' => 'Only certified tasks can be marked as paid.',
            ]);
        }

        $request->validate([
            'payment_ref_no' => ['required', 'string', 'max:255'],
            'attachments' => ['required', 'array', 'min:1'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        if (!$task->paymentSlip) {
            return back()->withErrors([
                'status' => 'Payment slip is required before payment.',
            ]);
        }

        if (!$task->paymentSlip->company_bank_account_id) {
            return back()->withErrors([
                'status' => 'Company bank account is required before payment.',
            ]);
        }

        foreach ($request->file('attachments', []) as $file) {
            AttachmentService::store($file, $task->paymentSlip);
        }

        $task->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_ref_no' => $request->payment_ref_no,
        ]);

        SubConTask::refreshParentProgressFor($task->parent_id);

        return back()->with('success', 'Marked as paid.');
    }

    private function getTask(Request $request, string $projectUuid, string $taskUuid): SubConTask
    {
        $project = $this->resolveProject($request, $projectUuid);

        return SubConTask::where('uuid', $taskUuid)
            ->where('project_id', $project->id)
            ->firstOrFail();
    }

    public function update(Request $request, string $projectUuid, string $taskUuid)
    {
        $this->ensureInternalUser($request);

        $project = $this->resolveProject($request, $projectUuid);

        $task = SubConTask::where('uuid', $taskUuid)
            ->where('project_id', $project->id)
            ->firstOrFail();
        $oldParentId = $task->parent_id;

        $validated = $request->validate([
            'sub_con_id' => 'required|integer|exists:sub_cons,id',
            'title' => 'required|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'parent_id' => 'nullable|integer|exists:sub_con_tasks,id',
        ]);

        $isBound = $project->subCons()
            ->where('sub_cons.id', $validated['sub_con_id'])
            ->exists();

        if (!$isBound) {
            return back()->withErrors([
                'sub_con_id' => 'Selected Sub Con is not bound to this project.',
            ]);
        }

        if (!empty($validated['parent_id'])) {
            $parent = SubConTask::where('id', $validated['parent_id'])
                ->where('project_id', $project->id)
                ->first();

            if (!$parent) {
                return back()->withErrors([
                    'parent_id' => 'Parent task does not belong to this project.',
                ]);
            }

            if ((int) $parent->id === (int) $task->id) {
                return back()->withErrors([
                    'parent_id' => 'Task cannot be its own parent.',
                ]);
            }

            if ($parent->parent_id) {
                return back()->withErrors([
                    'parent_id' => 'Parent task must be a main task.',
                ]);
            }
        }

        $task->update([
            'sub_con_id' => $validated['sub_con_id'],
            'title' => $validated['title'],
            'amount' => empty($validated['parent_id'])
                ? (float) ($validated['amount'] ?? 0)
                : 0,
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        SubConTask::refreshParentProgressFor($oldParentId);
        SubConTask::refreshParentProgressFor($task->parent_id);

        return back()->with('success', 'Sub Con task updated successfully.');
    }

    public function destroy(Request $request, string $projectUuid, string $taskUuid)
    {
        $this->ensureInternalUser($request);

        $task = $this->getTask($request, $projectUuid, $taskUuid);
        $parentId = $task->parent_id;

        // If deleting main task, delete all its child tasks together.
        if (!$task->parent_id) {
            $task->children()->delete();
        }

        $task->delete();

        SubConTask::refreshParentProgressFor($parentId);

        return back()->with('success', 'Sub Con task deleted successfully.');
    }

    public function downloadUpdate(Request $request, string $projectUuid, string $taskUuid, string $updateUuid)
    {
        $task = $this->getTask($request, $projectUuid, $taskUuid);
        $this->ensureSubConOwnsTask($request, $task);

        $update = SubConTaskUpdate::where('uuid', $updateUuid)
            ->where('sub_con_task_id', $task->id)
            ->firstOrFail();

        if (!$update->attachment_path) {
            abort(404, 'Attachment not found.');
        }

        if (!Storage::disk('public')->exists($update->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        $name = $update->attachment_name ?? basename($update->attachment_path);

        return Storage::disk('public')->download($update->attachment_path, $name);
    }

    public function downloadInvoice(Request $request, string $projectUuid, string $taskUuid)
    {
        $this->ensureInternalUser($request);

        $task = $this->getTask($request, $projectUuid, $taskUuid);

        if (!$task->invoice_attachment_path) {
            abort(404, 'Invoice attachment not found.');
        }

        if (!Storage::disk('public')->exists($task->invoice_attachment_path)) {
            abort(404, 'Invoice attachment not found.');
        }

        $name = $task->invoice_attachment_name ?? basename($task->invoice_attachment_path);

        return Storage::disk('public')->download($task->invoice_attachment_path, $name);
    }

    private function resolveProject(Request $request, string $projectUuid): Project
    {
        $query = Project::where('uuid', $projectUuid);
        $this->scopeToActiveBranch($request, $query, 'branch_id');

        return $query->firstOrFail();
    }

    private function ensureInternalUser(Request $request): void
    {
        if ($request->user()?->sub_con_id) {
            abort(403, 'Sub Con account is not allowed to perform this action.');
        }
    }

    private function ensureSubConOwnsTask(Request $request, SubConTask $task): void
    {
        $subConId = $request->user()?->sub_con_id;

        if (!$subConId) {
            return;
        }

        if ((int) $subConId !== (int) $task->sub_con_id) {
            abort(403, 'You are not allowed to access this task.');
        }
    }

    private function ensureCeoOrGm(Request $request): void
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthenticated.');
        }

        if ($user->isSuperAdmin() || (bool) $user->is_general_manager) {
            return;
        }

        $activeRoleName = strtolower((string) ($user->activeRole?->name ?? ''));
        if (in_array($activeRoleName, ['ceo', 'gm', 'general manager'], true)) {
            return;
        }

        abort(403, 'Only CEO/GM can approve this task.');
    }

    private function scopeToActiveBranch(Request $request, Builder $query, string $column = 'branch_id'): void
    {
        $user = $request->user();

        if (!$user || !$this->shouldScopeToActiveBranch($request) || $user->sub_con_id) {
            return;
        }

        $branchId = (int) ($user->active_branch_id ?? 0);
        if ($branchId <= 0) {
            abort(403, 'Active branch is required.');
        }

        $query->where($column, $branchId);
    }

    private function shouldScopeToActiveBranch(Request $request): bool
    {
        return !$request->user()?->isSuperAdmin() || !$request->boolean('all_branches');
    }
}
