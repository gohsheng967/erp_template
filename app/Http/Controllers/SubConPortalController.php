<?php

namespace App\Http\Controllers;

use App\Models\SubCon;
use App\Models\SubConClaim;
use App\Models\Project;
use App\Models\SubConTask;
use App\Models\SubConTaskUpdate;
use App\Services\RunningNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $claims = SubConClaim::query()
            ->with(['project:id,uuid,code,name,status'])
            ->where('sub_con_id', $subCon->id)
            ->orderByDesc('id')
            ->get();
        $claimProjects = $subCon->projects()
            ->orderBy('projects.code')
            ->orderBy('projects.name')
            ->get(['projects.uuid', 'projects.code', 'projects.name']);

        $mainTasks = $tasks->whereNull('parent_id');

        $stats = [
            'total' => $mainTasks->count(),
            'draft' => $mainTasks->where('status', 'draft')->count(),
            'submitted' => $mainTasks->where('status', 'submitted')->count(),
            'verified' => $mainTasks->whereIn('status', ['verified', 'contra_verified', 'invoiced', 'approved', 'justified', 'certified', 'paid'])->count(),
        ];
        $claimStats = [
            'total' => $claims->count(),
            'pending_decision' => $claims->where('status', 'ceo_gm_approved')->count(),
            'appealed' => $claims->where('status', 'appealed')->count(),
            'pending_real_invoice_upload' => $claims->where('status', 'pending_real_invoice_upload')->count(),
            'completed' => $claims->where('status', 'real_invoice_uploaded')->count(),
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
            'claimProjects' => $claimProjects,
            'claimStats' => $claimStats,
            'claims' => $claims->map(function (SubConClaim $claim) {
                return [
                    'uuid' => $claim->uuid,
                    'claim_no' => $claim->claim_no,
                    'title' => $claim->title,
                    'status' => $claim->status,
                    'claimed_amount' => (float) ($claim->claimed_amount ?? 0),
                    'approved_amount' => $claim->approved_amount !== null ? (float) $claim->approved_amount : null,
                    'payment_slip_no' => $claim->payment_slip_no,
                    'appeal_round' => (int) ($claim->appeal_round ?? 0),
                    'proforma_invoice_name' => $claim->proforma_invoice_name,
                    'real_invoice_name' => $claim->real_invoice_name,
                    'project' => $claim->project ? [
                        'uuid' => $claim->project->uuid,
                        'name' => $claim->project->name,
                        'code' => $claim->project->code,
                    ] : null,
                    'created_at' => optional($claim->created_at)?->toDateTimeString(),
                    'updated_at' => optional($claim->updated_at)?->toDateTimeString(),
                    'remark_log' => is_array($claim->remark_log) ? $claim->remark_log : [],
                ];
            })->values(),
        ]);
    }

    public function storeClaim(Request $request)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $validated = $request->validate([
            'project_uuid' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'claimed_amount' => ['required', 'numeric', 'min:0'],
            'proforma_invoice' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $project = Project::query()
            ->where('uuid', $validated['project_uuid'])
            ->whereHas('subCons', fn ($q) => $q->where('sub_cons.id', $subCon->id))
            ->first();

        if (!$project) {
            return back()->withErrors([
                'project_uuid' => 'Selected project is not available for your account.',
            ]);
        }

        $branchSlug = DB::table('branches')
            ->where('id', (int) $project->branch_id)
            ->value('slug');

        if (!$branchSlug) {
            return back()->withErrors([
                'project_uuid' => 'Project branch is invalid. Please contact admin.',
            ]);
        }

        $file = $request->file('proforma_invoice');
        $path = $file->store('sub-con-claims/proforma', 'public');

        $claim = SubConClaim::create([
            'claim_no' => RunningNumberService::next(
                'sub_con_claim',
                null,
                (int) $project->branch_id,
                (string) $branchSlug
            ),
            'project_id' => $project->id,
            'sub_con_id' => $subCon->id,
            'title' => $validated['title'],
            'status' => 'submitted',
            'claimed_amount' => (float) $validated['claimed_amount'],
            'proforma_invoice_path' => $path,
            'proforma_invoice_name' => $file->getClientOriginalName(),
            'submitted_at' => now(),
        ]);

        $this->appendClaimLog(
            $claim,
            from: null,
            to: 'submitted',
            action: 'submit',
            by: $subCon->name,
            remark: $validated['remark'] ?? null
        );

        return back()->with('success', 'Claim submitted successfully.');
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

    public function decideClaim(Request $request, string $claimUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $claim = SubConClaim::query()
            ->where('uuid', $claimUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        if ($claim->status !== 'ceo_gm_approved') {
            return back()->withErrors([
                'status' => 'This claim is not waiting for Sub Con decision.',
            ]);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:accept,appeal'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['decision'] === 'appeal' && empty(trim((string) ($validated['remark'] ?? '')))) {
            return back()->withErrors([
                'remark' => 'Appeal remark is required.',
            ]);
        }

        $to = $validated['decision'] === 'accept' ? 'accepted_by_subcon' : 'appealed';

        $claim->update([
            'status' => $to,
            'appeal_round' => $to === 'appealed' ? ((int) $claim->appeal_round + 1) : $claim->appeal_round,
            'subcon_decided_at' => now(),
        ]);

        $this->appendClaimLog(
            $claim,
            from: 'ceo_gm_approved',
            to: $to,
            action: 'subcon_' . $validated['decision'],
            by: $subCon->name,
            remark: $validated['remark'] ?? null
        );

        return back()->with('success', $to === 'accepted_by_subcon'
            ? 'You accepted the approved amount.'
            : 'Appeal submitted. Our team will review again.');
    }

    public function uploadClaimRealInvoice(Request $request, string $claimUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $claim = SubConClaim::query()
            ->where('uuid', $claimUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        if ($claim->status !== 'pending_real_invoice_upload') {
            return back()->withErrors([
                'status' => 'Real invoice upload is not open yet for this claim.',
            ]);
        }

        $validated = $request->validate([
            'real_invoice_no' => ['required', 'string', 'max:100'],
            'real_invoice_date' => ['required', 'date'],
            'real_invoice_amount' => ['required', 'numeric', 'min:0'],
            'real_invoice' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        if (!empty($claim->real_invoice_path)) {
            Storage::disk('public')->delete($claim->real_invoice_path);
        }

        $file = $request->file('real_invoice');
        $path = $file->store('sub-con-claims/real-invoice', 'public');

        $claim->update([
            'status' => 'real_invoice_uploaded',
            'real_invoice_no' => $validated['real_invoice_no'],
            'real_invoice_date' => $validated['real_invoice_date'],
            'real_invoice_amount' => (float) $validated['real_invoice_amount'],
            'real_invoice_path' => $path,
            'real_invoice_name' => $file->getClientOriginalName(),
            'real_invoice_uploaded_at' => now(),
        ]);

        $this->appendClaimLog(
            $claim,
            from: 'pending_real_invoice_upload',
            to: 'real_invoice_uploaded',
            action: 'upload_real_invoice',
            by: $subCon->name,
            remark: $validated['remark'] ?? null,
            amounts: ['real_invoice_amount' => (float) $validated['real_invoice_amount']]
        );

        return back()->with('success', 'Real invoice uploaded successfully.');
    }

    public function downloadClaimProforma(Request $request, string $claimUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $claim = SubConClaim::query()
            ->where('uuid', $claimUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        if (!$claim->proforma_invoice_path || !Storage::disk('public')->exists($claim->proforma_invoice_path)) {
            abort(404, 'Proforma invoice not found.');
        }

        return Storage::disk('public')->download(
            $claim->proforma_invoice_path,
            $claim->proforma_invoice_name ?: basename($claim->proforma_invoice_path)
        );
    }

    public function downloadClaimRealInvoice(Request $request, string $claimUuid)
    {
        $subCon = $this->getAuthenticatedSubCon($request);

        $claim = SubConClaim::query()
            ->where('uuid', $claimUuid)
            ->where('sub_con_id', $subCon->id)
            ->firstOrFail();

        if (!$claim->real_invoice_path || !Storage::disk('public')->exists($claim->real_invoice_path)) {
            abort(404, 'Real invoice not found.');
        }

        return Storage::disk('public')->download(
            $claim->real_invoice_path,
            $claim->real_invoice_name ?: basename($claim->real_invoice_path)
        );
    }

    private function getAuthenticatedSubCon(Request $request): SubCon
    {
        $subConId = (int) $request->session()->get('sub_con_auth_id');

        return SubCon::query()
            ->where('id', $subConId)
            ->firstOrFail();
    }

    private function appendClaimLog(
        SubConClaim $claim,
        ?string $from,
        string $to,
        string $action,
        string $by,
        ?string $remark = null,
        array $amounts = []
    ): void {
        $log = is_array($claim->remark_log) ? $claim->remark_log : [];
        $log[] = [
            'from' => $from,
            'to' => $to,
            'action' => $action,
            'remark' => $remark,
            'by' => $by,
            'at' => now()->toDateTimeString(),
            'amounts' => $amounts,
        ];

        $claim->forceFill(['remark_log' => $log])->save();
    }
}

